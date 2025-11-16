@extends('device-registration.layout', [
    'stages' => $stages,
    'currentStage' => 4,
])

@section('content')
    <h1>Awaiting Approval</h1>
    <p class="description">Your device registration is complete. An administrator will review and approve your request shortly.</p>

    <div style="background: #f1f5f9; border-radius: 12px; padding: 1.25rem; color: #1c1d21; line-height: 1.6;">
        <p style="margin: 0;">You will receive a notification on this device once the approval process is finished. You may close this window safely.</p>
    </div>
@endsection

