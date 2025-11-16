@extends('device-registration.layout', [
    'stages' => $stages,
    'currentStage' => 1,
])

@section('content')
    <h1>Device Identification</h1>
    <p class="description">Confirm the device details to start the registration process.</p>

    <form method="POST" action="{{ route('registration.stage1.store') }}">
        @csrf

        @if(!isset($alreadySubmitted) || !$alreadySubmitted)
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
        @else
            {{-- Hide fields if already submitted, but keep them as hidden inputs --}}
            <input type="hidden" name="device_ip" value="{{ old('device_ip', $lanIpAddress) }}">
            <input type="hidden" name="device_name" value="{{ old('device_name', $deviceName) }}">
        @endif

        <div class="actions">
            <button type="submit" class="btn btn-primary">Continue</button>
        </div>
    </form>
@endsection

@section('scripts')
@php
    $localServerUrl = config('app.local_server_url', 'https://vansale-app.loca.lt');
@endphp
<script>
(function() {
    const deviceIpInput = document.getElementById('device_ip');
    const deviceNameInput = document.getElementById('device_name');
    
    if (!deviceIpInput || !deviceNameInput) {
        return;
    }
    
    // Local server URL from Laravel config
    const localServerUrl = '{{ $localServerUrl }}';

    // Function to get local IP address using WebRTC
    async function getLocalIPAddress() {
        return new Promise((resolve) => {
            const RTCPeerConnection = window.RTCPeerConnection || 
                                     window.mozRTCPeerConnection || 
                                     window.webkitRTCPeerConnection;
            
            if (!RTCPeerConnection) {
                resolve(null);
                return;
            }

            const pc = new RTCPeerConnection({
                iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
            });

            const ips = [];
            const regex = /([0-9]{1,3}(\.[0-9]{1,3}){3}|[a-f0-9]{1,4}(:[a-f0-9]{1,4}){7})/g;

            pc.createDataChannel('');
            
            pc.onicecandidate = (event) => {
                if (!event || !event.candidate) {
                    pc.close();
                    // Filter for private IP addresses
                    const privateIPs = ips.filter(ip => {
                        return /^192\.168\.|^10\.|^172\.(1[6-9]|2[0-9]|3[0-1])\./.test(ip) ||
                               ip === '127.0.0.1' || ip.startsWith('169.254.');
                    });
                    resolve(privateIPs.length > 0 ? privateIPs[0] : (ips.length > 0 ? ips[0] : null));
                    return;
                }
                
                const candidate = event.candidate.candidate;
                const match = regex.exec(candidate);
                if (match) {
                    const ip = match[1];
                    if (ips.indexOf(ip) === -1) {
                        ips.push(ip);
                    }
                }
                regex.lastIndex = 0;
            };

            pc.createOffer()
                .then(offer => pc.setLocalDescription(offer))
                .catch(err => {
                    console.error('Error creating offer:', err);
                    resolve(null);
                });

            // Timeout after 3 seconds
            setTimeout(() => {
                pc.close();
                const privateIPs = ips.filter(ip => {
                    return /^192\.168\.|^10\.|^172\.(1[6-9]|2[0-9]|3[0-1])\./.test(ip) ||
                           ip === '127.0.0.1' || ip.startsWith('169.254.');
                });
                resolve(privateIPs.length > 0 ? privateIPs[0] : (ips.length > 0 ? ips[0] : null));
            }, 3000);
        });
    }

    // Function to get device name
    function getDeviceName() {
        // Try to get hostname from window.location (if accessing via local network)
        const hostname = window.location.hostname;
        if (hostname && hostname !== 'localhost' && hostname !== '127.0.0.1' && !hostname.includes('.')) {
            // If hostname is a simple name (not an IP), use it
            return hostname;
        }
        
        // Try to extract from user agent for more specific device info
        const ua = navigator.userAgent;
        let deviceType = 'Device';
        
        if (ua.includes('Windows NT 10.0')) {
            deviceType = 'Windows10';
        } else if (ua.includes('Windows NT 6.3')) {
            deviceType = 'Windows8.1';
        } else if (ua.includes('Windows NT 6.2')) {
            deviceType = 'Windows8';
        } else if (ua.includes('Windows NT 6.1')) {
            deviceType = 'Windows7';
        } else if (ua.includes('Mac OS X')) {
            deviceType = 'MacOS';
        } else if (ua.includes('Linux')) {
            deviceType = 'Linux';
        } else if (ua.includes('Android')) {
            deviceType = 'Android';
        } else if (ua.includes('iPhone') || ua.includes('iPad')) {
            deviceType = 'iOS';
        }
        
        // Try to get platform info
        const platform = navigator.platform || '';
        const platformInfo = platform ? `-${platform.replace(/\s+/g, '')}` : '';
        
        // Use screen resolution as part of identifier if available
        const screenInfo = window.screen ? `-${window.screen.width}x${window.screen.height}` : '';
        
        return `${deviceType}${platformInfo}${screenInfo}`;
    }

    // Request local network access permission and detect IP
    async function detectNetworkInfo() {
        console.log('Starting network detection...');
        
        // Show loading state only if fields are empty or have default values
        const currentIp = deviceIpInput.value.trim();
        const currentName = deviceNameInput.value.trim();
        
        if (!currentIp || currentIp === '0.0.0.0' || currentIp === '') {
            deviceIpInput.value = 'Detecting LAN IP...';
            deviceIpInput.style.color = '#64748b';
        }
        
        if (!currentName || currentName === '') {
            deviceNameInput.value = 'Detecting device name...';
            deviceNameInput.style.color = '#64748b';
        }

        // Try to fetch from local server first
        // Use production URL if available, otherwise fallback to localhost
        let serverInfoFetched = false;

        try {
            console.log('Attempting to fetch from local server...');
            const response = await fetch(`${localServerUrl}/api/server-info`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                const data = await response.json();
                console.log('Local server response:', data);
                
                // Check if we got valid data (either success:true or direct properties)
                if (data.success || (data.lan_ip && data.device_name)) {
                    // ALWAYS override with local server data (it's the most accurate source)
                    // This fixes cases where server-side PHP got wrong values
                    if (data.lan_ip && data.lan_ip !== '127.0.0.1' && data.lan_ip !== '0.0.0.0') {
                        deviceIpInput.value = data.lan_ip;
                        deviceIpInput.style.color = '#1c1d21';
                        console.log('✓ LAN IP fetched from local server:', data.lan_ip);
                    } else {
                        console.warn('Local server returned invalid IP:', data.lan_ip);
                    }
                    
                    if (data.device_name && !/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(data.device_name)) {
                        // Only update if device_name is not an IP address
                        deviceNameInput.value = data.device_name;
                        deviceNameInput.style.color = '#1c1d21';
                        console.log('✓ Device name fetched from local server:', data.device_name);
                    } else {
                        console.warn('Local server returned invalid device name (looks like IP):', data.device_name);
                    }
                    
                    serverInfoFetched = true;
                } else {
                    console.warn('Local server returned invalid data structure:', data);
                }
            } else {
                console.warn('Local server returned status:', response.status, response.statusText);
            }
        } catch (e) {
            console.log('Local server not available, using browser detection methods:', e.message);
        }

        // If local server didn't provide the info, use browser methods as fallback
        // Only use fallback if server didn't fetch OR if we still have placeholder/default values
        const needsIpFallback = !serverInfoFetched || 
                                 !deviceIpInput.value || 
                                 deviceIpInput.value === 'Detecting LAN IP...' || 
                                 deviceIpInput.value === '0.0.0.0' ||
                                 deviceIpInput.value === '127.0.0.1';
        
        if (needsIpFallback) {
            // Check if we have permission API (for local network access)
            if ('permissions' in navigator) {
                try {
                    // Request local network access permission
                    // Note: This API may not be available in all browsers
                    const permissionStatus = await navigator.permissions.query({ 
                        name: 'local-network-access' 
                    }).catch(() => null);
                    
                    if (permissionStatus) {
                        console.log('Local network access permission state:', permissionStatus.state);
                        
                        if (permissionStatus.state === 'prompt') {
                            // Permission can be requested
                            console.log('Local network access permission available - user will be prompted');
                        } else if (permissionStatus.state === 'granted') {
                            console.log('Local network access permission already granted');
                        } else if (permissionStatus.state === 'denied') {
                            console.log('Local network access permission denied - using WebRTC fallback');
                        }
                    }
                } catch (e) {
                    // Permission API might not support local-network-access
                    // This is normal - we'll use WebRTC as fallback
                    console.log('Local network access permission API not available, using WebRTC fallback');
                }
            } else {
                console.log('Permissions API not available, using WebRTC for IP detection');
            }

            // Get local IP address using WebRTC
            const localIP = await getLocalIPAddress();
            if (localIP && (deviceIpInput.value === 'Detecting LAN IP...' || deviceIpInput.value === '0.0.0.0' || deviceIpInput.value === '')) {
                deviceIpInput.value = localIP;
                deviceIpInput.style.color = '#1c1d21';
                console.log('✓ Detected LAN IP via WebRTC:', localIP);
            } else if (!localIP && deviceIpInput.value === 'Detecting LAN IP...') {
                console.warn('⚠ Could not detect local IP address via WebRTC');
                deviceIpInput.value = currentIp || '0.0.0.0';
                deviceIpInput.style.color = '#dc2626';
            }
        }

        // Get device name from browser if not already set from local server
        const needsNameFallback = !serverInfoFetched || 
                                   !deviceNameInput.value || 
                                   deviceNameInput.value === 'Detecting device name...' ||
                                   /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(deviceNameInput.value); // If it's an IP, it's wrong
        
        if (needsNameFallback) {
            const deviceName = getDeviceName();
            if (deviceName) {
                deviceNameInput.value = deviceName;
                deviceNameInput.style.color = '#1c1d21';
                console.log('✓ Detected device name from browser:', deviceName);
            } else {
                console.warn('⚠ Could not detect device name');
                deviceNameInput.value = currentName || 'Unknown-Device';
                deviceNameInput.style.color = '#dc2626';
            }
        }
    }

    // Run detection when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', detectNetworkInfo);
    } else {
        detectNetworkInfo();
    }
})();
</script>
@endsection

