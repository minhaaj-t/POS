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

