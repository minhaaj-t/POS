@extends('device-registration.layout', [
    'stages' => $stages,
    'currentStage' => 1,
])

@section('content')
    <h1>Device Identification</h1>
    <p class="description">Confirm the device details to start the registration process.</p>

    <form method="POST" action="{{ route('registration.stage1.store') }}">
        @csrf

        <div>
            <label for="device_ip">Device IP</label>
            <input
                id="device_ip"
                name="device_ip"
                type="text"
                value="{{ old('device_ip', $lanIpAddress) }}"
                readonly
            >
            <small style="color: #64748b; font-size: 0.85rem; display: block; margin-top: 0.25rem;">Auto-detected. If incorrect, please contact support.</small>
            @error('device_ip')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="device_name">Device Name <span style="color: #dc2626;">*</span></label>
            <input
                id="device_name"
                name="device_name"
                type="text"
                value="{{ old('device_name', $deviceName) }}"
                placeholder="e.g., DESKTOP-3DVN5FM"
                required
            >
            <small style="color: #64748b; font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                Enter your Windows computer name (hostname). You can find it by running <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">hostname</code> in Command Prompt.
            </small>
            @error('device_name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Continue</button>
        </div>
    </form>
@endsection

@section('scripts')
    <script>
        (function() {
            const deviceIpInput = document.getElementById('device_ip');
            const deviceNameInput = document.getElementById('device_name');
            
            // Function to check if IP is private
            function isPrivateIP(ip) {
                if (!ip || typeof ip !== 'string') return false;
                return ip.startsWith('192.168.') || 
                       ip.startsWith('10.') || 
                       (ip.startsWith('172.') && 
                        parseInt(ip.split('.')[1]) >= 16 && 
                        parseInt(ip.split('.')[1]) <= 31) ||
                       ip.startsWith('169.254.'); // Link-local
            }
            
            // Function to check if a string is an IP address
            function isIPAddress(str) {
                if (!str || typeof str !== 'string') return false;
                const ipRegex = /^(\d{1,3}\.){3}\d{1,3}$/;
                return ipRegex.test(str);
            }
            
            // Function to get IP from server API (most reliable)
            async function getIPFromServer() {
                try {
                    const response = await fetch('{{ route("registration.detect.ip") }}');
                    const data = await response.json();
                    if (data.success && data.ip) {
                        return data.ip;
                    }
                } catch (error) {
                    console.error('Failed to get IP from server:', error);
                }
                return '';
            }
            
            // Function to get local IP using WebRTC (fallback method)
            function getLocalIPWebRTC() {
                return new Promise((resolve) => {
                    const RTCPeerConnection = window.RTCPeerConnection || 
                                             window.mozRTCPeerConnection || 
                                             window.webkitRTCPeerConnection;
                    
                    if (!RTCPeerConnection) {
                        resolve('');
                        return;
                    }
                    
                    const pc = new RTCPeerConnection({
                        iceServers: [
                            { urls: 'stun:stun.l.google.com:19302' },
                            { urls: 'stun:stun1.l.google.com:19302' },
                            { urls: 'stun:stun2.l.google.com:19302' }
                        ]
                    });
                    
                    pc.createDataChannel('');
                    
                    const candidates = [];
                    const seenIPs = new Set();
                    
                    pc.onicecandidate = (event) => {
                        if (event.candidate) {
                            const candidate = event.candidate.candidate;
                            // Match host candidate (local IPs)
                            const hostMatch = candidate.match(/host\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/);
                            if (hostMatch) {
                                const ip = hostMatch[1];
                                if (!seenIPs.has(ip)) {
                                    seenIPs.add(ip);
                                    candidates.push(ip);
                                }
                            }
                        } else {
                            // No more candidates
                            pc.close();
                            
                            // Filter for private IPs only, exclude link-local
                            const privateIPs = candidates.filter(ip => {
                                return isPrivateIP(ip) && !ip.startsWith('169.254.');
                            });
                            
                            if (privateIPs.length > 0) {
                                // Prefer 192.168.x.x, then 10.x.x.x, then 172.x.x.x
                                const preferred = privateIPs.find(ip => ip.startsWith('192.168.')) ||
                                                privateIPs.find(ip => ip.startsWith('10.')) ||
                                                privateIPs[0];
                                resolve(preferred);
                            } else {
                                resolve('');
                            }
                        }
                    };
                    
                    pc.createOffer()
                        .then(offer => pc.setLocalDescription(offer))
                        .catch(() => {
                            pc.close();
                            resolve('');
                        });
                    
                    // Timeout after 3 seconds
                    setTimeout(() => {
                        if (!pc.closed) {
                            pc.close();
                        }
                        
                        const privateIPs = candidates.filter(ip => {
                            return isPrivateIP(ip) && !ip.startsWith('169.254.');
                        });
                        
                        if (privateIPs.length > 0) {
                            const preferred = privateIPs.find(ip => ip.startsWith('192.168.')) ||
                                            privateIPs.find(ip => ip.startsWith('10.')) ||
                                            privateIPs[0];
                            resolve(preferred);
                        } else {
                            resolve('');
                        }
                    }, 3000);
                });
            }
            
            // Function to get device name - ensure it's never an IP address
            function getDeviceName() {
                // Check if we have a stored device name in localStorage
                let deviceName = localStorage.getItem('device_name');
                
                // Validate stored name - reject if it's an IP address
                if (deviceName && deviceName !== '' && !isIPAddress(deviceName)) {
                    return deviceName;
                }
                
                // Clear invalid stored name
                if (isIPAddress(deviceName)) {
                    localStorage.removeItem('device_name');
                }
                
                // Try to get from server value if valid
                const serverValue = deviceNameInput.value;
                if (serverValue && serverValue !== '' && !isIPAddress(serverValue) && 
                    !serverValue.includes('Chrome') && !serverValue.includes('Firefox') &&
                    !serverValue.includes('Windows-')) {
                    localStorage.setItem('device_name', serverValue);
                    return serverValue;
                }
                
                // Generate a device identifier based on browser fingerprint
                const parts = [];
                
                // Get OS/platform info
                const platform = navigator.platform || '';
                if (platform && platform !== '') {
                    parts.push(platform.replace(/\s+/g, '-'));
                }
                
                // Add a unique identifier based on screen resolution and timezone
                const screenInfo = `${screen.width}x${screen.height}`;
                const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
                const hash = btoa(screenInfo + timezone + navigator.language).substring(0, 6).replace(/[^a-zA-Z0-9]/g, '');
                
                if (parts.length > 0) {
                    deviceName = parts.join('-') + '-' + hash;
                } else {
                    deviceName = 'Device-' + hash;
                }
                
                // Store it for future use
                localStorage.setItem('device_name', deviceName);
                
                return deviceName;
            }
            
            // Initialize detection
            async function detectDeviceInfo() {
                // Detect IP address - WebRTC is primary method for LAN IP
                const currentIP = deviceIpInput.value;
                const shouldDetectIP = !currentIP || 
                                      currentIP === '' || 
                                      currentIP === '0.0.0.0' || 
                                      currentIP === '127.0.0.1' ||
                                      currentIP === 'Unable to detect' ||
                                      !isPrivateIP(currentIP);
                
                if (shouldDetectIP) {
                    // WebRTC is the primary method for detecting LAN IP
                    const localIP = await getLocalIPWebRTC();
                    if (localIP) {
                        deviceIpInput.value = localIP;
                    } else {
                        // Fallback to server API (may return public IP)
                        const serverIP = await getIPFromServer();
                        if (serverIP) {
                            deviceIpInput.value = serverIP;
                        } else if (!deviceIpInput.value || deviceIpInput.value === '') {
                            deviceIpInput.value = 'Unable to detect';
                        }
                    }
                }
                
                // Detect device name - ensure it's never an IP address
                const currentDeviceName = deviceNameInput.value;
                
                // If current value is an IP address or invalid, replace it
                if (isIPAddress(currentDeviceName) || 
                    !currentDeviceName || 
                    currentDeviceName === '' || 
                    currentDeviceName.includes('Chrome') || 
                    currentDeviceName.includes('Firefox') ||
                    (currentDeviceName.includes('Windows-') && currentDeviceName.split('-').length === 2)) {
                    
                    const deviceName = getDeviceName();
                    if (deviceName) {
                        deviceNameInput.value = deviceName;
                    }
                }
            }
            
            // Run detection when page loads
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', detectDeviceInfo);
            } else {
                detectDeviceInfo();
            }
        })();
    </script>
@endsection

