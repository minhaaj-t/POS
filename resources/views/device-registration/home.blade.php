@extends('device-registration.layout', [
    'stages' => [],
    'currentStage' => 0,
])

@section('content')
    <h1>Welcome</h1>
    <p class="description">Your device has been successfully registered and approved.</p>

    <div style="background: #d1fae5; border: 2px solid #10b981; border-radius: 12px; padding: 1.5rem; color: #065f46; margin-bottom: 1.5rem;">
        <p style="margin: 0 0 0.5rem 0; font-weight: 600; font-size: 1.1rem;">✓ Registration Complete</p>
        <p style="margin: 0; font-size: 0.95rem;">Your device is now ready to use.</p>
    </div>

    <div style="background: #f1f5f9; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <h2 style="margin: 0 0 1rem 0; font-size: 1.1rem; color: #1c1d21;">Device Information</h2>
        <div style="display: grid; gap: 0.75rem;">
            <div>
                <strong>Device Name:</strong> {{ $deviceName }}
            </div>
            <div>
                <strong>LAN IP Address:</strong> {{ $deviceIp }}
            </div>
            <div>
                <strong>Employee ID:</strong> {{ $employeeId }}
            </div>
            <div>
                <strong>Outlet Name:</strong> {{ $outletName }}
            </div>
        </div>
    </div>

    <div class="actions">
        <a href="{{ route('registration.stage1') }}" class="btn btn-secondary">Register Another Device</a>
    </div>
@endsection

