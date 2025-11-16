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
            // Get fresh data from local server or fallback methods
            $lanIp = $this->getLanIpAddress();
            $deviceName = $this->getDeviceName();
            
            // Validate device name (should not be an IP address)
            if (filter_var($deviceName, FILTER_VALIDATE_IP)) {
                Log::warning("Device name appears to be an IP address", ['value' => $deviceName]);
            }
            
            // Validate IP (should not be localhost)
            if ($lanIp === '127.0.0.1' || $lanIp === '0.0.0.0') {
                Log::warning("LAN IP is localhost or invalid", ['ip' => $lanIp]);
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
            'manager_name' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
        ]);

        $request->session()->put('registration.stage3', $data);

        // Store registration data in RPOS_LOGIN table
        $registrationResult = $this->storeRegistrationInDatabase($request);
        
        if (!$registrationResult['success']) {
            Log::error("Failed to store registration in database", [
                'error' => $registrationResult['message'] ?? 'Unknown error',
            ]);
            // Continue to waiting page even if database insert fails
            // The admin can manually approve later
        } else {
            Log::info("Registration data stored successfully in RPOS_LOGIN", [
                'device_id' => $registrationResult['device_id'] ?? 'N/A',
            ]);
        }

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
            $response = Http::timeout(5)->get("{$pythonServerUrl}/api/user/{$employeeId}");
            
            if ($response->successful()) {
                $data = $response->json();
                
                $isValid = ($data['found'] ?? false) || ($data['success'] ?? false);
                
                if ($isValid) {
                    $dbPassword = $data['password'] 
                        ?? $data['user']['password'] 
                        ?? $data['data']['password'] 
                        ?? null;
                    
                    $locationCode = $data['location_code'] 
                        ?? $data['user']['location_code'] 
                        ?? $data['data']['location_code']
                        ?? $data['locationCode']
                        ?? null;
                    
                    // Compare passwords (case-sensitive)
                    $valid = $dbPassword !== null && $dbPassword === $enteredPassword;
                    
                    return [
                        'valid' => $valid,
                        'location_code' => $locationCode,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("Employee validation exception", [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
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
        
        // Try production API endpoint first (singular)
        $endpoints = [
            "{$pythonServerUrl}/api/location/{$locationCode}",  // Production API format
            "{$pythonServerUrl}/api/locations/{$locationCode}", // Fallback (local server format)
        ];
        
        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(5)->get($endpoint);
                
                if ($response->successful()) {
                    $data = $response->json();
                    $isValid = ($data['found'] ?? false) || ($data['success'] ?? false);
                    
                    if ($isValid) {
                        $location = $data['location'] 
                            ?? $data['data']
                            ?? (isset($data['location_name']) || isset($data['locationName']) ? $data : null)
                            ?? null;
                        
                        if ($location) {
                            Log::info("Location details retrieved", [
                                'location_code' => $locationCode,
                                'location_name' => $location['location_name'] ?? $location['locationName'] ?? 'N/A',
                            ]);
                            return $location;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Try next endpoint
                continue;
            }
        }
        
        return null;
    }

    public function waiting(Request $request): View|RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if (! $request->session()->has('registration.credentials')) {
            return redirect()->route('registration.stage1');
        }

        // Get device information from session to check approval status
        $stage1Data = $request->session()->get('registration.stage1', []);
        $stage2Data = $request->session()->get('registration.stage2', []);
        
        $deviceId = $request->input('device_id') ?? $stage1Data['device_name'] ?? null;
        $employeeId = $request->input('employee_id') ?? $stage2Data['employee_id'] ?? null;
        
        // If this is an AJAX request for status check, return JSON
        if ($request->input('check_status') == '1' || $request->wantsJson()) {
            $approvalStatus = null;
            if ($deviceId) {
                $approvalStatus = $this->checkApprovalStatus($deviceId, $employeeId);
            }
            
            return response()->json([
                'approved' => $approvalStatus === 'Y',
                'approval_flag' => $approvalStatus,
                'device_id' => $deviceId,
                'employee_id' => $employeeId,
            ]);
        }
        
        // Check approval status for regular page load
        $approvalStatus = null;
        if ($deviceId && $employeeId) {
            $approvalStatus = $this->checkApprovalStatus($deviceId, $employeeId);
        }

        return view('device-registration.waiting', [
            'stages' => $this->stages(),
            'currentStage' => 4,
            'isApproved' => $approvalStatus === 'Y',
            'approvalStatus' => $approvalStatus,
            'stage1Data' => $stage1Data,
            'stage2Data' => $stage2Data,
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
            $response = Http::timeout(3)->get("{$pythonServerUrl}/api/user/{$employeeId}");
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (($data['found'] ?? false) || ($data['success'] ?? false)) {
                    $normalizedData = [
                        'success' => true,
                        'employee' => [
                            'id' => $data['employee_id'] ?? $data['id'] ?? $employeeId,
                            'name' => $data['name'] ?? $data['employee']['name'] ?? '',
                        ],
                        'username' => $data['username'] ?? $data['employee_id'] ?? $employeeId,
                    ];
                    
                    if (isset($data['location_code'])) {
                        $normalizedData['location_code'] = $data['location_code'];
                    }
                    if (isset($data['counter'])) {
                        $normalizedData['counter'] = $data['counter'];
                    }
                    
                    return response()->json($normalizedData);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Employee service unavailable: " . $e->getMessage());
        }
        
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
     * Store registration data in RPOS_LOGIN table
     */
    private function storeRegistrationInDatabase(Request $request): array
    {
        // Get all registration data from session
        $stage1Data = $request->session()->get('registration.stage1', []);
        $stage2Data = $request->session()->get('registration.stage2', []);
        $stage3Data = $request->session()->get('registration.stage3', []);
        
        // Validate that we have all required data
        if (empty($stage1Data) || empty($stage2Data) || empty($stage3Data)) {
            return [
                'success' => false,
                'message' => 'Missing registration data in session',
            ];
        }
        
        // Map data to RPOS_LOGIN table columns
        $registrationData = [
            'device_id' => $stage1Data['device_name'] ?? '',           // DEVICE_ID
            'employee_id' => $stage2Data['employee_id'] ?? '',         // EMPLOYEE_ID
            'admin_employee_id' => $stage2Data['username'] ?? $stage2Data['employee_id'] ?? '', // ADMIN_EMPLOYEE_ID
            'lan_ip' => $stage1Data['device_ip'] ?? '',                // LAN_IP
            'approval_flag' => 'N',                                     // APPROVAL_FLAG (default 'N')
        ];
        
        // Validate required fields
        if (empty($registrationData['device_id']) || 
            empty($registrationData['employee_id']) || 
            empty($registrationData['lan_ip'])) {
            return [
                'success' => false,
                'message' => 'Missing required registration fields',
                'data' => $registrationData,
            ];
        }
        
        // Call production server API to insert data
        $pythonServerUrl = env('PYTHON_SERVER_URL', 'https://vansale-app.loca.lt');
        
        try {
            Log::info("Storing registration in RPOS_LOGIN", [
                'data' => $registrationData,
                'endpoint' => "{$pythonServerUrl}/api/rpos-login",
            ]);
            
            $response = Http::timeout(10)->post("{$pythonServerUrl}/api/rpos-login", $registrationData);
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info("RPOS_LOGIN insert response", [
                    'response' => $data,
                ]);
                
                // Handle different response formats
                $isSuccess = ($data['success'] ?? false) || 
                           ($data['found'] ?? false) || 
                           ($response->status() === 200 || $response->status() === 201);
                
                if ($isSuccess) {
                    return [
                        'success' => true,
                        'message' => 'Registration stored successfully',
                        'device_id' => $registrationData['device_id'],
                        'response' => $data,
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $data['message'] ?? 'Failed to store registration',
                        'response' => $data,
                    ];
                }
            } else {
                Log::warning("RPOS_LOGIN insert HTTP error", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return [
                    'success' => false,
                    'message' => "HTTP error: {$response->status()}",
                    'body' => $response->body(),
                ];
            }
        } catch (\Exception $e) {
            Log::error("RPOS_LOGIN insert exception", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check approval status from RPOS_LOGIN table
     */
    private function checkApprovalStatus(string $deviceId, string $employeeId): ?string
    {
        $pythonServerUrl = env('PYTHON_SERVER_URL', 'https://vansale-app.loca.lt');
        
        try {
            // Check approval status endpoint
            // Try different possible endpoint formats
            $endpoints = [
                "{$pythonServerUrl}/api/rpos-login/status?device_id={$deviceId}&employee_id={$employeeId}",
                "{$pythonServerUrl}/api/rpos-login/status?device_id={$deviceId}",
            ];
            
            foreach ($endpoints as $endpoint) {
                try {
                    $response = Http::timeout(5)->get($endpoint);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        
                        if (isset($data['approval_flag'])) {
                            return $data['approval_flag'];
                        } elseif (isset($data['approvalFlag'])) {
                            return $data['approvalFlag'];
                        } elseif (isset($data['status']) && $data['status'] === 'approved') {
                            return 'Y';
                        } elseif (isset($data['approved']) && $data['approved'] === true) {
                            return 'Y';
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        } catch (\Exception $e) {
            // Silent fail - will return null
        }
        
        return null;
    }

    /**
     * Home page after approval
     */
    public function home(Request $request): View
    {
        // Get registration data from session
        $stage1Data = $request->session()->get('registration.stage1', []);
        $stage2Data = $request->session()->get('registration.stage2', []);
        $stage3Data = $request->session()->get('registration.stage3', []);
        
        return view('device-registration.home', [
            'deviceName' => $stage1Data['device_name'] ?? 'N/A',
            'deviceIp' => $stage1Data['device_ip'] ?? 'N/A',
            'employeeId' => $stage2Data['employee_id'] ?? 'N/A',
            'outletName' => $stage3Data['outlet_name'] ?? 'N/A',
        ]);
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

