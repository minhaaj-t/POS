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
                Enter your Windows computer name (hostname). Use the terminal below to get it automatically.
            </small>
            @error('device_name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <!-- Terminal Interface -->
        <div style="margin-top: 1.5rem; border: 1px solid #d0d5dd; border-radius: 10px; overflow: hidden; background: #1e1e1e;">
            <div style="background: #2d2d2d; padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #3d3d3d;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: #ff5f57;"></span>
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: #ffbd2e;"></span>
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: #28ca42;"></span>
                    <span style="margin-left: 0.75rem; color: #d4d4d4; font-size: 0.85rem; font-weight: 500;">Terminal</span>
                </div>
                <button type="button" id="auto-detect-btn" style="background: #2563eb; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.8rem; cursor: pointer; font-weight: 500;">
                    Auto-Detect
                </button>
            </div>
            <div id="terminal-output" style="padding: 1rem; color: #d4d4d4; font-family: 'Consolas', 'Monaco', monospace; font-size: 0.9rem; min-height: 200px; max-height: 300px; overflow-y: auto; background: #1e1e1e;">
                <div style="color: #4ec9b0;">Welcome to Device Detection Terminal</div>
                <div style="margin-top: 0.5rem; color: #808080;">Type commands or click "Auto-Detect" to automatically detect IP and hostname.</div>
                <div style="margin-top: 0.75rem; color: #808080;">Available commands: <span style="color: #4ec9b0;">hostname</span>, <span style="color: #4ec9b0;">ipconfig</span>, <span style="color: #4ec9b0;">get-hostname</span>, <span style="color: #4ec9b0;">get-ip</span></div>
                <div id="terminal-content" style="margin-top: 1rem;"></div>
                <div style="display: flex; align-items: center; margin-top: 1rem;">
                    <span style="color: #4ec9b0;">$</span>
                    <input 
                        type="text" 
                        id="terminal-input" 
                        style="flex: 1; background: transparent; border: none; color: #d4d4d4; font-family: 'Consolas', 'Monaco', monospace; font-size: 0.9rem; padding: 0.25rem 0.5rem; outline: none;"
                        placeholder="Enter command..."
                        autocomplete="off"
                    >
                </div>
            </div>
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
            
            // Terminal functionality
            const terminalInput = document.getElementById('terminal-input');
            const terminalContent = document.getElementById('terminal-content');
            const autoDetectBtn = document.getElementById('auto-detect-btn');
            
            function addTerminalLine(text, type = 'output') {
                const line = document.createElement('div');
                line.style.marginTop = '0.5rem';
                line.style.wordBreak = 'break-word';
                if (type === 'command') {
                    line.style.color = '#4ec9b0';
                } else if (type === 'error') {
                    line.style.color = '#f48771';
                } else if (type === 'success') {
                    line.style.color = '#4ec9b0';
                    line.style.fontWeight = '500';
                } else {
                    line.style.color = '#d4d4d4';
                }
                line.textContent = text;
                terminalContent.appendChild(line);
                terminalContent.scrollTop = terminalContent.scrollHeight;
            }
            
            function executeCommand(command) {
                addTerminalLine(`$ ${command}`, 'command');
                
                fetch('{{ route("registration.execute.command") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ command: command })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addTerminalLine(data.output);
                        
                        // Auto-fill fields based on command output
                        if (command === 'hostname' || command === 'get-hostname') {
                            const hostname = data.output.trim();
                            if (hostname && !isIPAddress(hostname)) {
                                deviceNameInput.value = hostname;
                                localStorage.setItem('device_name', hostname);
                                addTerminalLine('✓ Device name updated!', 'success');
                            }
                        } else if (command === 'get-ip') {
                            const ip = data.output.trim();
                            if (ip && isPrivateIP(ip)) {
                                deviceIpInput.value = ip;
                                addTerminalLine('✓ IP address updated!', 'success');
                            }
                        } else if (command === 'ipconfig') {
                            // Parse ipconfig output for IPv4 addresses
                            const ipRegex = /IPv4 Address[.\s]+:\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/g;
                            const matches = [...data.output.matchAll(ipRegex)];
                            const privateIPs = matches
                                .map(m => m[1])
                                .filter(ip => isPrivateIP(ip) && !ip.startsWith('169.254.'));
                            
                            if (privateIPs.length > 0) {
                                const preferred = privateIPs.find(ip => ip.startsWith('192.168.')) ||
                                                privateIPs.find(ip => ip.startsWith('10.')) ||
                                                privateIPs[0];
                                deviceIpInput.value = preferred;
                                addTerminalLine(`✓ IP address updated: ${preferred}`, 'success');
                            }
                        }
                    } else {
                        addTerminalLine(`Error: ${data.error}`, 'error');
                    }
                })
                .catch(error => {
                    addTerminalLine(`Error: ${error.message}`, 'error');
                });
            }
            
            // Auto-detect function
            autoDetectBtn.addEventListener('click', function() {
                addTerminalLine('Starting auto-detection...', 'command');
                addTerminalLine('');
                
                // Detect hostname
                executeCommand('hostname');
                
                setTimeout(() => {
                    executeCommand('get-ip');
                }, 500);
            });
            
            // Terminal input handler
            terminalInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const command = this.value.trim();
                    if (command) {
                        executeCommand(command);
                        this.value = '';
                    }
                }
            });
            
            // Command shortcuts
            const commandMap = {
                'hostname': 'hostname',
                'ip': 'get-ip',
                'ipconfig': 'ipconfig',
                'name': 'get-hostname',
            };
            
            terminalInput.addEventListener('input', function() {
                const value = this.value.trim().toLowerCase();
                if (commandMap[value]) {
                    // Show suggestion
                }
            });
        })();
    </script>
    <style>
        #terminal-content .success {
            color: #4ec9b0 !important;
        }
    </style>
@endsection

