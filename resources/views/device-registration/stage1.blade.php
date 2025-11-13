@extends('device-registration.layout', [
    'stages' => $stages,
    'currentStage' => 1,
])

@section('content')
    <h1>Device Identification</h1>
    <p class="description">Confirm the device details to start the registration process.</p>

    @if(!$batchFileRunning)
        <div id="batch-file-error" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; color: #dc2626;">
            <strong style="display: block; margin-bottom: 0.5rem;">⚠️ Batch File Not Running</strong>
            <p style="margin: 0; font-size: 0.9rem;">{{ $batchFileError ?? 'Your bat file is not running. Please ensure device-monitor.bat is running in background.' }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('registration.stage1.store') }}" id="registration-form">
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
            <button type="submit" class="btn btn-primary" id="continue-btn" {{ !$batchFileRunning ? 'disabled' : '' }}>Continue</button>
        </div>
    </form>
@endsection

@section('scripts')
<script>
    // Check batch file status periodically
    let batchFileCheckInterval;
    
    function checkBatchFileStatus() {
        fetch('{{ route("registration.batchfile.status") }}')
            .then(response => response.json())
            .then(data => {
                const errorDiv = document.getElementById('batch-file-error');
                const continueBtn = document.getElementById('continue-btn');
                const form = document.getElementById('registration-form');
                
                if (!data.running) {
                    // Show error message
                    if (!errorDiv) {
                        const container = document.querySelector('.container');
                        const newErrorDiv = document.createElement('div');
                        newErrorDiv.id = 'batch-file-error';
                        newErrorDiv.style.cssText = 'background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; color: #dc2626;';
                        newErrorDiv.innerHTML = `
                            <strong style="display: block; margin-bottom: 0.5rem;">⚠️ Batch File Not Running</strong>
                            <p style="margin: 0; font-size: 0.9rem;">${data.error || 'Your bat file is not running. Please ensure device-monitor.bat is running in background.'}</p>
                        `;
                        container.insertBefore(newErrorDiv, form);
                    } else {
                        errorDiv.querySelector('p').textContent = data.error || 'Your bat file is not running. Please ensure device-monitor.bat is running in background.';
                    }
                    
                    // Disable continue button
                    if (continueBtn) {
                        continueBtn.disabled = true;
                        continueBtn.style.opacity = '0.6';
                        continueBtn.style.cursor = 'not-allowed';
                    }
                } else {
                    // Hide error message
                    if (errorDiv) {
                        errorDiv.remove();
                    }
                    
                    // Enable continue button
                    if (continueBtn) {
                        continueBtn.disabled = false;
                        continueBtn.style.opacity = '1';
                        continueBtn.style.cursor = 'pointer';
                    }
                }
            })
            .catch(error => {
                console.error('Error checking batch file status:', error);
            });
    }
    
    // Check status every 10 seconds
    batchFileCheckInterval = setInterval(checkBatchFileStatus, 10000);
    
    // Initial check after page load
    setTimeout(checkBatchFileStatus, 1000);
    
    // Prevent form submission if batch file is not running
    document.getElementById('registration-form').addEventListener('submit', function(e) {
        const continueBtn = document.getElementById('continue-btn');
        if (continueBtn && continueBtn.disabled) {
            e.preventDefault();
            alert('Cannot proceed: Batch file is not running. Please ensure device-monitor.bat is running in background.');
            return false;
        }
    });
</script>
@endsection

