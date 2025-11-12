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
            <label for="device_name">Device Name</label>
            <input
                id="device_name"
                name="device_name"
                type="text"
                value="{{ old('device_name', $deviceName) }}"
            >
            <small style="color: #64748b; font-size: 0.85rem; display: block; margin-top: 0.25rem;">You can edit this if the auto-detected name is incorrect.</small>
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
                return ip.startsWith('192.168.') || 
                       ip.startsWith('10.') || 
                       (ip.startsWith('172.') && 
                        parseInt(ip.split('.')[1]) >= 16 && 
                        parseInt(ip.split('.')[1]) <= 31) ||
                       ip.startsWith('169.254.'); // Link-local
            }
            
            // Function to get local IP using WebRTC
            function getLocalIP() {
                return new Promise((resolve) => {
                    const RTCPeerConnection = window.RTCPeerConnection || 
                                             window.mozRTCPeerConnection || 
                                             window.webkitRTCPeerConnection;
                    
                    if (!RTCPeerConnection) {
                        resolve('');
                        return;
                    }
                    
                    const pc = new RTCPeerConnection({
                        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
                    });
                    
                    pc.createDataChannel('');
                    
                    const candidates = [];
                    let foundPrivateIP = null;
                    
                    pc.onicecandidate = (event) => {
                        if (event.candidate) {
                            const candidate = event.candidate.candidate;
                            const match = candidate.match(/([0-9]{1,3}(\.[0-9]{1,3}){3})/);
                            if (match) {
                                const ip = match[1];
                                candidates.push(ip);
                                
                                // Prioritize private IPs - return immediately if found
                                if (isPrivateIP(ip) && !foundPrivateIP) {
                                    foundPrivateIP = ip;
                                    // Don't close yet, wait for more candidates to ensure we have the best one
                                }
                            }
                        } else {
                            // No more candidates - process what we have
                            pc.close();
                            
                            // First priority: private IPs
                            const privateIPs = candidates.filter(ip => isPrivateIP(ip));
                            if (privateIPs.length > 0) {
                                // Return the first private IP found (usually the LAN IP)
                                resolve(foundPrivateIP || privateIPs[0]);
                            } else {
                                // No private IP found
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
                    
                    // Timeout after 5 seconds to give more time for candidates
                    setTimeout(() => {
                        if (!pc.closed) {
                            pc.close();
                        }
                        
                        // Process collected candidates
                        const privateIPs = candidates.filter(ip => isPrivateIP(ip));
                        if (privateIPs.length > 0) {
                            resolve(privateIPs[0]);
                        } else {
                            resolve('');
                        }
                    }, 5000);
                });
            }
            
            // Function to get public IP
            function getPublicIP() {
                return fetch('https://api.ipify.org?format=json')
                    .then(response => response.json())
                    .then(data => data.ip || '')
                    .catch(() => '');
            }
            
            // Function to get device name
            function getDeviceName() {
                // Check if we have a stored device name in localStorage
                let deviceName = localStorage.getItem('device_name');
                if (deviceName && deviceName !== '') {
                    return deviceName;
                }
                
                // Try to construct a meaningful device name
                const parts = [];
                
                // Get platform/OS info
                if (navigator.userAgentData && navigator.userAgentData.platform) {
                    parts.push(navigator.userAgentData.platform);
                } else if (navigator.platform) {
                    parts.push(navigator.platform.replace(/\s+/g, '-'));
                }
                
                // Get browser info
                const ua = navigator.userAgent;
                if (ua.includes('Chrome') && !ua.includes('Edg')) {
                    parts.push('Chrome');
                } else if (ua.includes('Firefox')) {
                    parts.push('Firefox');
                } else if (ua.includes('Safari') && !ua.includes('Chrome')) {
                    parts.push('Safari');
                } else if (ua.includes('Edg')) {
                    parts.push('Edge');
                }
                
                // If we have parts, join them
                if (parts.length > 0) {
                    deviceName = parts.join('-');
                } else {
                    // Last resort: use a generic name with a short hash
                    const hash = Math.random().toString(36).substring(2, 8);
                    deviceName = 'Device-' + hash;
                }
                
                // Store it for future use
                localStorage.setItem('device_name', deviceName);
                
                return deviceName;
            }
            
            // Initialize detection
            async function detectDeviceInfo() {
                // Detect device name - only override if server didn't provide one
                const serverDeviceName = deviceNameInput.value;
                if (!serverDeviceName || serverDeviceName === '' || serverDeviceName === 'Device-' || serverDeviceName.includes('Chrome') || serverDeviceName.includes('Firefox')) {
                    // Server didn't provide a valid name, use JavaScript detection
                    const deviceName = getDeviceName();
                    if (deviceName) {
                        deviceNameInput.value = deviceName;
                    }
                }
                
                // Detect IP address
                // If IP is already set and valid, check if we should override
                const currentIP = deviceIpInput.value;
                
                // Check if current IP is public (not a private LAN IP)
                const isPublicIP = currentIP && 
                                  currentIP !== '' && 
                                  currentIP !== '0.0.0.0' && 
                                  currentIP !== '127.0.0.1' &&
                                  currentIP !== 'Unable to detect' &&
                                  !isPrivateIP(currentIP);
                
                // Always try to get local LAN IP, even if we have a public IP
                const localIP = await getLocalIP();
                if (localIP) {
                    // Found local LAN IP - use it instead of public IP
                    deviceIpInput.value = localIP;
                    return;
                }
                
                // If we don't have a valid IP yet, try public IP as fallback
                if (!currentIP || currentIP === '' || currentIP === '0.0.0.0' || currentIP === '127.0.0.1' || currentIP === 'Unable to detect') {
                    const publicIP = await getPublicIP();
                    if (publicIP) {
                        deviceIpInput.value = publicIP;
                    } else {
                        deviceIpInput.value = 'Unable to detect';
                    }
                }
                // If we already have a public IP and no local IP found, keep the public IP
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

