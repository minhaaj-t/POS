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
        $lanIp = $this->getLanIpAddress();
        $deviceName = $this->getDeviceName();

        $request->session()->put('registration.stage1', [
            'device_ip' => $lanIp,
            'device_name' => $deviceName,
        ]);

        return view('device-registration.stage1', [
            'lanIpAddress' => $lanIp,
            'deviceName' => $deviceName,
            'stages' => $this->stages(),
            'currentStage' => 1,
        ]);
    }

    public function storeStageOne(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'device_ip' => ['required', 'ip'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

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
        $pythonServerUrl = env('PYTHON_SERVER_URL', 'http://localhost:5000');
        
        try {
            $response = Http::timeout(5)->get("{$pythonServerUrl}/api/employees/{$employeeId}");
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['success'] ?? false) {
                    $dbPassword = $data['password'] ?? null;
                    $locationCode = $data['location_code'] ?? null;
                    
                    Log::info("Employee data retrieved", [
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
        $pythonServerUrl = env('PYTHON_SERVER_URL', 'http://localhost:5000');
        
        try {
            $response = Http::timeout(5)->get("{$pythonServerUrl}/api/locations/{$locationCode}");
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['success'] ?? false) {
                    $location = $data['location'] ?? null;
                    
                    if ($location) {
                        Log::info("Location details retrieved successfully", [
                            'location_code' => $locationCode,
                            'location_name' => $location['location_name'] ?? 'N/A',
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
        // First, try to get client IP from forwarded headers (for production behind proxy/load balancer)
        $request = request();
        
        // Check various forwarded headers in order of preference
        $forwardedHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED',          // Alternative
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Alternative
            'HTTP_FORWARDED',            // Standard
        ];
        
        foreach ($forwardedHeaders as $header) {
            $ip = $request->server($header);
            if ($ip) {
                // X-Forwarded-For can contain multiple IPs, get the first one
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
                
                // Validate IP (both private and public)
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip;
                }
            }
        }
        
        // Fallback: try to get server's local IP (for local development)
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

        // Last resort: get from REMOTE_ADDR
        $remoteAddr = $request->server('REMOTE_ADDR', '0.0.0.0');
        
        // If it's localhost/127.0.0.1, return empty to let JavaScript handle it
        if (in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'])) {
            return '';
        }
        
        return $remoteAddr;
    }

    private function getDeviceName(): string
    {
        // Try to get hostname from server (works on localhost/local network)
        $hostname = @gethostname();
        
        if ($hostname && $hostname !== '' && $hostname !== 'localhost' && $hostname !== '127.0.0.1') {
            return $hostname;
        }

        // Try shell commands as fallback
        $commands = [
            'hostname',
            'hostnamectl hostname 2>/dev/null',
            'uname -n 2>/dev/null',
        ];

        foreach ($commands as $command) {
            $output = @shell_exec($command);
            
            if ($output) {
                $name = trim($output);
                if ($name !== '' && $name !== 'localhost') {
                    return $name;
                }
            }
        }

        // Return empty to let JavaScript handle it as fallback
        return '';
    }

    public function detectClientIP(Request $request)
    {
        // Note: Server can only see client's public IP or forwarded IP
        // For LAN IP detection, WebRTC on client-side is required
        // This endpoint is mainly for validation/fallback
        $ip = $this->getClientIPFromRequest($request);
        
        return response()->json([
            'success' => true,
            'ip' => $ip,
            'is_private' => $this->isPrivateIP($ip),
            'note' => 'For LAN IP, use WebRTC on client-side. This returns the IP as seen by the server.',
        ]);
    }
    
    private function getClientIPFromRequest(Request $request): string
    {
        // Check various forwarded headers in order of preference
        $forwardedHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED',          // Alternative
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Alternative
            'HTTP_FORWARDED',            // Standard
        ];
        
        foreach ($forwardedHeaders as $header) {
            $ip = $request->server($header);
            if ($ip) {
                // X-Forwarded-For can contain multiple IPs, get the first one
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
                
                // Validate IP (both private and public)
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        $remoteAddr = $request->server('REMOTE_ADDR', '');
        if (filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $remoteAddr;
        }
        
        return '';
    }
    
    private function isPrivateIP(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        
        // Check if it's a private IP range
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    public function getEmployeeById(Request $request, string $employeeId)
    {
        // Try to fetch from Python Oracle server first
        $pythonServerUrl = env('PYTHON_SERVER_URL', 'http://localhost:5000');
        
        try {
            $response = Http::timeout(3)->get("{$pythonServerUrl}/api/employees/{$employeeId}");
            
            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] ?? false) {
                    return response()->json($data);
                }
            }
        } catch (\Exception $e) {
            // Log error but continue to fallback
            Log::warning("Python server unavailable, using fallback: " . $e->getMessage());
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

