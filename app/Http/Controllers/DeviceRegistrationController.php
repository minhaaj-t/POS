<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DeviceRegistrationController extends Controller
{
    public function stageOne(Request $request): View
    {
        // Check if user has already submitted and confirmed stage 1
        $stage1Data = $request->session()->get('registration.stage1');
        $alreadySubmitted = isset($stage1Data['confirmed']) && $stage1Data['confirmed'] === true;

        if ($alreadySubmitted && isset($stage1Data['device_ip']) && isset($stage1Data['device_name'])) {
            // Use session data if already submitted and confirmed
            $lanIp = $stage1Data['device_ip'];
            $deviceName = $stage1Data['device_name'];
        } else {
            // Always try to get fresh data from local server first
            // This ensures we get the most accurate information
            $lanIp = $this->getLanIpAddress();
            $deviceName = $this->getDeviceName();
            
            // Validate that we got reasonable values
            // If device name looks like an IP, it's wrong - try again
            if (filter_var($deviceName, FILTER_VALIDATE_IP)) {
                Log::warning("Device name appears to be an IP address, retrying...", ['value' => $deviceName]);
                $deviceName = $this->getDeviceName();
            }
            
            // If IP is localhost, try again
            if ($lanIp === '127.0.0.1' || $lanIp === '0.0.0.0') {
                Log::warning("LAN IP is localhost or invalid, retrying...", ['ip' => $lanIp]);
                $lanIp = $this->getLanIpAddress();
            }
        }

        return view('device-registration.stage1', [
            'lanIpAddress' => $lanIp,
            'deviceName' => $deviceName,
            'stages' => $this->stages(),
            'currentStage' => 1,
            'alreadySubmitted' => $alreadySubmitted,
        ]);
    }

    public function storeStageOne(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'device_ip' => ['required', 'ip'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        // Mark as confirmed when user submits the form
        $data['confirmed'] = true;
        $request->session()->put('registration.stage1', $data);

        return redirect()->route('registration.stage2');
    }

    public function stageTwo(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('registration.stage1')) {
            return redirect()->route('registration.stage1');
        }

        $defaults = $request->session()->get('registration.stage2', [
            'employee_id' => '',
            'username' => '',
        ]);

        return view('device-registration.stage3', [
            'form' => $defaults,
            'stages' => $this->stages(),
            'currentStage' => 2,
        ]);
    }

    public function storeStageTwo(Request $request): RedirectResponse
    {
        if (! $request->session()->has('registration.stage1')) {
            return redirect()->route('registration.stage1');
        }

        $data = $request->validate([
            'employee_id' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        // Validate password against database and get location code
        $employeeData = $this->getEmployeeDataForValidation($data['employee_id'], $data['password']);
        
        if (! $employeeData['valid']) {
            return redirect()->back()
                ->withErrors(['password' => 'The password does not match the database password.'])
                ->withInput();
        }

        $request->session()->put('registration.stage2', Arr::except($data, ['password', 'password_confirmation']));
        $request->session()->put('registration.credentials', [
            'username' => $data['username'],
            'password' => $data['password'],
        ]);
        
        // Store location code for fetching shop details
        if (isset($employeeData['location_code']) && $employeeData['location_code'] !== null) {
            $request->session()->put('registration.location_code', $employeeData['location_code']);
            Log::info("Location code stored in session", [
                'employee_id' => $data['employee_id'],
                'location_code' => $employeeData['location_code'],
            ]);
        } else {
            Log::warning("Location code not found in employee data", [
                'employee_id' => $data['employee_id'],
                'employee_data' => $employeeData,
            ]);
        }

        return redirect()->route('registration.stage3');
    }

    public function stageThree(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('registration.stage2')) {
            return redirect()->route('registration.stage2');
        }

        // Fetch location details from database
        $locationCode = $request->session()->get('registration.location_code');
        $locationData = null;
        
        if ($locationCode) {
            $locationData = $this->getLocationDetails($locationCode);
            
            // Log for debugging
            if ($locationData) {
                Log::info("Location data fetched", ['location_code' => $locationCode, 'data' => $locationData]);
            } else {
                Log::warning("Location data not found", ['location_code' => $locationCode]);
            }
        } else {
            Log::warning("Location code not found in session");
        }

        // Use location data if available, otherwise use session data
        $sessionStage3 = $request->session()->get('registration.stage3', []);
        
        $defaults = [
            'outlet_name' => ($locationData && isset($locationData['location_name'])) ? $locationData['location_name'] : ($sessionStage3['outlet_name'] ?? ''),
            'manager_name' => ($locationData && isset($locationData['manager'])) ? $locationData['manager'] : ($sessionStage3['manager_name'] ?? ''),
            'address' => ($locationData && isset($locationData['address'])) ? $locationData['address'] : ($sessionStage3['address'] ?? ''),
        ];

        return view('device-registration.stage2', [
            'form' => $defaults,
            'location' => $locationData,
            'stages' => $this->stages(),
            'currentStage' => 3,
        ]);
    }

    public function storeStageThree(Request $request): RedirectResponse
    {
        if (! $request->session()->has('registration.stage2')) {
            return redirect()->route('registration.stage2');
        }

        $data = $request->validate([
            'outlet_name' => ['required', 'string', 'max:255'],
            'manager_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
        ]);

        $request->session()->put('registration.stage3', $data);

        return redirect()->route('registration.waiting');
    }

    /**
     * Get employee data for validation and return location code
     */
    private function getEmployeeDataForValidation(string $employeeId, string $enteredPassword): array
    {
        $pythonServerUrl = env('PYTHON_SERVER_URL', 'https://vansale-app.loca.lt');
        
        try {
            // Production server uses /api/user/<employee_code> instead of /api/employees/<employee_id>
            // Disable SSL verification for localtunnel URLs (self-signed certificates)
            $url = "{$pythonServerUrl}/api/user/{$employeeId}";
            
            Log::info("Fetching employee data", [
                'employee_id' => $employeeId,
                'url' => $url,
            ]);
            
            $response = Http::timeout(10)
                ->withoutVerifying() // Disable SSL verification for localtunnel
                ->get($url);
            
            Log::info("Employee API response received", [
                'employee_id' => $employeeId,
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info("Employee API response data", [
                    'employee_id' => $employeeId,
                    'response' => $data,
                ]);
                
                // Handle different response formats
                // Production API might use 'found' instead of 'success'
                // Format 1: {success: true, password: "...", location_code: ...}
                // Format 2: {found: true, password: "...", location_code: ...}
                // Format 3: {success: true, user: {...}, password: "...", location_code: ...}
                
                $isValid = ($data['success'] ?? false) || ($data['found'] ?? false);
                
                if ($isValid) {
                    // Try different possible locations for password and location_code
                    $dbPassword = $data['password'] 
                        ?? $data['user']['password'] 
                        ?? $data['data']['password'] 
                        ?? null;
                    
                    $locationCode = $data['location_code'] 
                        ?? $data['user']['location_code'] 
                        ?? $data['data']['location_code']
                        ?? $data['locationCode']
                        ?? null;
                    
                    Log::info("Employee data parsed successfully", [
                        'employee_id' => $employeeId,
                        'has_password' => $dbPassword !== null,
                        'location_code' => $locationCode,
                    ]);
                    
                    // Compare passwords (case-sensitive)
                    $valid = $dbPassword !== null && $dbPassword === $enteredPassword;
                    
                    return [
                        'valid' => $valid,
                        'location_code' => $locationCode,
                    ];
                } else {
                    Log::warning("Employee fetch returned unsuccessful", [
                        'employee_id' => $employeeId,
                        'success' => $data['success'] ?? null,
                        'found' => $data['found'] ?? null,
                        'response' => $data,
                    ]);
                }
            } else {
                Log::warning("Employee fetch HTTP error", [
                    'employee_id' => $employeeId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Employee API connection exception", [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'url' => $url ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            Log::error("Employee validation exception", [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        return ['valid' => false, 'location_code' => null];
    }

    /**
     * Get location details from Oracle database
     */
    private function getLocationDetails(int $locationCode): ?array
    {
        $pythonServerUrl = env('PYTHON_SERVER_URL', 'https://vansale-app.loca.lt');
        
        try {
            // Production server uses /api/location/<location_code> instead of /api/locations/<location_code>
            // Disable SSL verification for localtunnel URLs
            $url = "{$pythonServerUrl}/api/location/{$locationCode}";
            
            $response = Http::timeout(10)
                ->withoutVerifying() // Disable SSL verification for localtunnel
                ->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info("Location API response", [
                    'location_code' => $locationCode,
                    'response' => $data,
                ]);
                
                if ($data['success'] ?? false) {
                    // Handle different response formats
                    // Format 1: {success: true, location: {...}}
                    // Format 2: {success: true, data: {...}}
                    $location = $data['location'] 
                        ?? $data['data'] 
                        ?? null;
                    
                    if ($location) {
                        Log::info("Location details retrieved successfully", [
                            'location_code' => $locationCode,
                            'location_name' => $location['location_name'] ?? $location['locationName'] ?? 'N/A',
                        ]);
                    }
                    
                    return $location;
                } else {
                    Log::warning("Location fetch returned unsuccessful", [
                        'location_code' => $locationCode,
                        'response' => $data,
                    ]);
                }
            } else {
                Log::warning("Location fetch HTTP error", [
                    'location_code' => $locationCode,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Location fetch exception", [
                'location_code' => $locationCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        return null;
    }

    public function waiting(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('registration.credentials')) {
            return redirect()->route('registration.stage1');
        }

        return view('device-registration.waiting', [
            'stages' => $this->stages(),
            'currentStage' => 4,
        ]);
    }

    private function getLanIpAddress(): string
    {
        // Try to fetch from local server first
        $localServerUrl = env('LOCAL_SERVER_URL', 'http://localhost:5001');
        
        try {
            $response = Http::timeout(2)->get("{$localServerUrl}/api/lan-ip");
            
            if ($response->successful()) {
                $data = $response->json();
                if (($data['success'] ?? false) && isset($data['lan_ip'])) {
                    $ip = $data['lan_ip'];
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        Log::info("LAN IP fetched from local server", ['ip' => $ip]);
                        return $ip;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log but continue to fallback methods
            Log::debug("Local server unavailable for IP detection, using fallback: " . $e->getMessage());
        }

        // Fallback to original methods
        $commands = [
            'ifconfig 2>/dev/null',
            'ipconfig',
        ];

        $isPrivate = static function (string $ip): bool {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return false;
            }

            return filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        };

        foreach ($commands as $command) {
            $output = @shell_exec($command);

            if (! $output) {
                continue;
            }

            if (preg_match_all('/\b((?:\d{1,3}\.){3}\d{1,3})\b/', $output, $matches)) {
                foreach ($matches[1] as $candidate) {
                    if ($isPrivate($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        return request()->server('REMOTE_ADDR', '0.0.0.0');
    }

    private function getDeviceName(): string
    {
        // Try to fetch from local server first
        $localServerUrl = env('LOCAL_SERVER_URL', 'http://localhost:5001');
        
        try {
            $response = Http::timeout(2)->get("{$localServerUrl}/api/device-name");
            
            if ($response->successful()) {
                $data = $response->json();
                if (($data['success'] ?? false) && isset($data['device_name'])) {
                    $deviceName = trim($data['device_name']);
                    if ($deviceName !== '') {
                        Log::info("Device name fetched from local server", ['device_name' => $deviceName]);
                        return $deviceName;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log but continue to fallback methods
            Log::debug("Local server unavailable for device name detection, using fallback: " . $e->getMessage());
        }

        // Fallback to original methods
        $hostname = @gethostname();
        
        if ($hostname && $hostname !== '') {
            return $hostname;
        }

        $commands = [
            'hostname',
            'hostnamectl hostname 2>/dev/null',
            'uname -n 2>/dev/null',
        ];

        foreach ($commands as $command) {
            $output = @shell_exec($command);
            
            if ($output) {
                $name = trim($output);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        $lanIp = $this->getLanIpAddress();
        return 'Device-' . str_replace('.', '-', $lanIp);
    }

    public function getEmployeeById(Request $request, string $employeeId)
    {
        // Try to fetch from Python Oracle server first
        $pythonServerUrl = env('PYTHON_SERVER_URL', 'https://vansale-app.loca.lt');
        
        try {
            // Production server uses /api/user/<employee_code> instead of /api/employees/<employee_id>
            // Disable SSL verification for localtunnel URLs
            $url = "{$pythonServerUrl}/api/user/{$employeeId}";
            
            Log::info("Fetching employee by ID", [
                'employee_id' => $employeeId,
                'url' => $url,
            ]);
            
            $response = Http::timeout(10)
                ->withoutVerifying() // Disable SSL verification for localtunnel
                ->get($url);
            
            Log::info("Employee by ID response received", [
                'employee_id' => $employeeId,
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info("Employee by ID response data", [
                    'employee_id' => $employeeId,
                    'response' => $data,
                ]);
                
                // Handle both 'success' and 'found' response formats
                $isValid = ($data['success'] ?? false) || ($data['found'] ?? false);
                
                if ($isValid) {
                    // Normalize response for frontend compatibility
                    // Frontend expects: {success: true, employee: {id: "...", name: "..."}, username: "..."}
                    $normalizedData = [
                        'success' => true,
                        'employee' => [
                            'id' => $data['employee_id'] 
                                ?? $data['id'] 
                                ?? $data['employee']['id'] 
                                ?? $employeeId,
                            'name' => $data['name'] 
                                ?? $data['employee']['name'] 
                                ?? $data['user']['name']
                                ?? $data['username']
                                ?? '',
                        ],
                        'username' => $data['username'] 
                            ?? $data['employee_id'] 
                            ?? $data['id']
                            ?? $employeeId,
                    ];
                    
                    // Include additional fields if present
                    if (isset($data['password'])) {
                        $normalizedData['password'] = $data['password'];
                    }
                    if (isset($data['location_code'])) {
                        $normalizedData['location_code'] = $data['location_code'];
                    }
                    if (isset($data['locationCode'])) {
                        $normalizedData['location_code'] = $data['locationCode'];
                    }
                    
                    Log::info("Normalized employee data for frontend", [
                        'employee_id' => $employeeId,
                        'normalized' => $normalizedData,
                    ]);
                    
                    return response()->json($normalizedData);
                } else {
                    Log::warning("Employee not found in API response", [
                        'employee_id' => $employeeId,
                        'response' => $data,
                    ]);
                }
            } else {
                Log::warning("Employee by ID HTTP error", [
                    'employee_id' => $employeeId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Employee API connection exception", [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'url' => $url ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            Log::error("Employee API exception", [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        // Return error if Python server is unavailable
        return $this->getEmployeeByIdFallback($employeeId);
    }
    
    /**
     * Fallback method when Python server is unavailable
     */
    private function getEmployeeByIdFallback(string $employeeId)
    {
        return response()->json([
            'success' => false,
            'message' => 'Employee service unavailable. Please ensure the Python Oracle server is running.',
        ], 503);
    }

    /**
     * @return array<int, string>
     */
    private function stages(): array
    {
        return [
            1 => 'Device Identify',
            2 => 'User Config',
            3 => 'Shop Details',
            4 => 'Waiting',
        ];
    }
}

