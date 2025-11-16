@extends('device-registration.layout', [
    'stages' => $stages,
    'currentStage' => 4,
])

@section('content')
    @if(isset($isApproved) && $isApproved)
        <h1>Registration Approved!</h1>
        <p class="description">Your device registration has been approved. You can now proceed to the home page.</p>

        <div style="background: #d1fae5; border: 2px solid #10b981; border-radius: 12px; padding: 1.25rem; color: #065f46; line-height: 1.6; margin-bottom: 1.5rem;">
            <p style="margin: 0; font-weight: 600;">✓ Your device has been successfully registered and approved.</p>
        </div>

        <div class="actions">
            <a href="{{ route('home') }}" class="btn btn-primary">Go to Home</a>
        </div>
    @else
        <h1>Awaiting Approval</h1>
        <p class="description">Your device registration is complete. An administrator will review and approve your request shortly.</p>

        <div style="background: #f1f5f9; border-radius: 12px; padding: 1.25rem; color: #1c1d21; line-height: 1.6;">
            <p style="margin: 0;">You will receive a notification on this device once the approval process is finished. You may close this window safely.</p>
        </div>

        <div style="margin-top: 1.5rem; padding: 0.85rem 0.95rem; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 10px;">
            <p style="margin: 0; color: #92400e; font-size: 0.9rem;">
                <strong>Status:</strong> Pending Approval (This page will automatically refresh to check approval status)
            </p>
        </div>
    @endif
@endsection

@section('scripts')
@if(!isset($isApproved) || !$isApproved)
<script>
    // Poll for approval status every 5 seconds
    let checkInterval;
    let checkCount = 0;
    const maxChecks = 120; // Stop after 10 minutes (120 * 5 seconds)
    
    function checkApprovalStatus() {
        checkCount++;
        
        // Get device and employee info from session
        const deviceId = @json($stage1Data['device_name'] ?? '');
        const employeeId = @json($stage2Data['employee_id'] ?? '');
        
        if (!deviceId) {
            console.warn('Device ID not available for approval check');
            return;
        }
        
        // Call the approval status check endpoint
        fetch(`{{ route('registration.waiting') }}?check_status=1&device_id=${encodeURIComponent(deviceId)}&employee_id=${encodeURIComponent(employeeId)}`)
            .then(response => response.json())
            .then(data => {
                if (data.approved === true || data.approval_flag === 'Y') {
                    // Approved! Reload page to show approved state
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error checking approval status:', error);
            });
        
        // Stop checking after max attempts
        if (checkCount >= maxChecks) {
            clearInterval(checkInterval);
            console.log('Stopped checking approval status after maximum attempts');
        }
    }
    
    // Start checking immediately, then every 5 seconds
    checkApprovalStatus();
    checkInterval = setInterval(checkApprovalStatus, 5000);
    
    // Also provide manual refresh option
    console.log('Approval status checking started. Page will auto-refresh when approved.');
</script>
@endif
@endsection

