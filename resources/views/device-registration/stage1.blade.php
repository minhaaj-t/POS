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
                readonly
            >
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
                    
                    pc.onicecandidate = (event) => {
                        if (event.candidate) {
                            const candidate = event.candidate.candidate;
                            const match = candidate.match(/([0-9]{1,3}(\.[0-9]{1,3}){3})/);
                            if (match) {
                                const ip = match[1];
                                // Check if it's a private IP
                                if (ip.startsWith('192.168.') || 
                                    ip.startsWith('10.') || 
                                    ip.startsWith('172.16.') || 
                                    ip.startsWith('172.17.') || 
                                    ip.startsWith('172.18.') || 
                                    ip.startsWith('172.19.') || 
                                    ip.startsWith('172.20.') || 
                                    ip.startsWith('172.21.') || 
                                    ip.startsWith('172.22.') || 
                                    ip.startsWith('172.23.') || 
                                    ip.startsWith('172.24.') || 
                                    ip.startsWith('172.25.') || 
                                    ip.startsWith('172.26.') || 
                                    ip.startsWith('172.27.') || 
                                    ip.startsWith('172.28.') || 
                                    ip.startsWith('172.29.') || 
                                    ip.startsWith('172.30.') || 
                                    ip.startsWith('172.31.')) {
                                    pc.close();
                                    resolve(ip);
                                    return;
                                }
                            }
                        }
                    };
                    
                    pc.createOffer()
                        .then(offer => pc.setLocalDescription(offer))
                        .catch(() => resolve(''));
                    
                    // Timeout after 3 seconds
                    setTimeout(() => {
                        pc.close();
                        resolve('');
                    }, 3000);
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
                // Always try to get device name (will use stored value if available)
                const deviceName = getDeviceName();
                if (deviceName && (deviceNameInput.value === '' || !deviceNameInput.value)) {
                    deviceNameInput.value = deviceName;
                }
                
                // Detect IP address
                // If IP is already set and valid, check if we should override
                const currentIP = deviceIpInput.value;
                const shouldDetectIP = !currentIP || 
                                      currentIP === '' || 
                                      currentIP === '0.0.0.0' || 
                                      currentIP === '127.0.0.1' ||
                                      currentIP === 'Unable to detect';
                
                if (!shouldDetectIP) {
                    return;
                }
                
                // Try to get local IP first (for LAN detection)
                const localIP = await getLocalIP();
                if (localIP) {
                    deviceIpInput.value = localIP;
                    return;
                }
                
                // Fallback to public IP
                const publicIP = await getPublicIP();
                if (publicIP) {
                    deviceIpInput.value = publicIP;
                } else if (!deviceIpInput.value || deviceIpInput.value === '') {
                    // Last resort: show message
                    deviceIpInput.value = 'Unable to detect';
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

