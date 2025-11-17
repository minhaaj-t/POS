@extends('device-registration.layout', [
    'stages' => $stages,
    'currentStage' => 3,
])

@section('content')
    <h1>Shop Details</h1>
    <p class="description">Confirm the shop details fetched from the database.</p>

    <form method="POST" action="{{ route('registration.stage3.store') }}">
        @csrf

        @if(isset($location) && $location)
            <div>
                <label for="outlet_name">Outlet Name</label>
                <input
                    id="outlet_name"
                    name="outlet_name"
                    type="text"
                    value="{{ old('outlet_name', $location['location_name'] ?? $form['outlet_name'] ?? '') }}"
                    readonly
                    required
                >
                @error('outlet_name')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="manager_name">Manager Name <span style="color: #64748b; font-weight: normal;">(Optional)</span></label>
                <input
                    id="manager_name"
                    name="manager_name"
                    type="text"
                    value="{{ old('manager_name', $location['manager'] ?? $form['manager_name'] ?? '') }}"
                    readonly
                >
                @error('manager_name')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="address">Address</label>
                <textarea
                    id="address"
                    name="address"
                    readonly
                    required
                >{{ old('address', $location['address'] ?? $form['address'] ?? '') }}</textarea>
                @error('address')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>
        @else
            <div style="padding: 1rem; background: #fff2f2; border: 1px solid #f53003; border-radius: 10px; margin-bottom: 1rem;">
                <p style="color: #f53003; margin: 0;">Unable to fetch shop details from database. Please check the Python server and ensure the location code is valid.</p>
            </div>
            
            <div>
                <label for="outlet_name">Outlet Name</label>
                <input
                    id="outlet_name"
                    name="outlet_name"
                    type="text"
                    value="{{ old('outlet_name', $form['outlet_name'] ?? '') }}"
                    required
                >
                @error('outlet_name')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="manager_name">Manager Name <span style="color: #64748b; font-weight: normal;">(Optional)</span></label>
                <input
                    id="manager_name"
                    name="manager_name"
                    type="text"
                    value="{{ old('manager_name', $form['manager_name'] ?? '') }}"
                >
                @error('manager_name')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="address">Address</label>
                <textarea
                    id="address"
                    name="address"
                    required
                >{{ old('address', $form['address'] ?? '') }}</textarea>
                @error('address')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>
        @endif

        @if(isset($location) && $location)
            <div style="padding: 0.85rem 0.95rem; background: #f1f5f9; border: 1px solid #d0d5dd; border-radius: 10px; margin-top: 1rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Contact Information</label>
                <div style="display: grid; gap: 0.5rem; font-size: 0.95rem;">
                    @if(!empty($location['email_id'] ?? $location['emailid'] ?? null))
                        <div><strong>Email:</strong> {{ $location['email_id'] ?? $location['emailid'] }}</div>
                    @endif
                    @if(!empty($location['fax']))
                        <div><strong>Fax:</strong> {{ $location['fax'] }}</div>
                    @endif
                    @if(!empty($location['telephone']))
                        <div><strong>Telephone:</strong> {{ $location['telephone'] }}</div>
                    @endif
                </div>
            </div>
        @endif

        <div class="actions">
            <a href="{{ route('registration.stage2') }}" class="btn btn-secondary">Back</a>
            <button type="submit" class="btn btn-primary">Confirm</button>
        </div>
    </form>
@endsection

