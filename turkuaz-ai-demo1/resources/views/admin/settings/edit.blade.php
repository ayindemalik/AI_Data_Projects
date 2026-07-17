@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
    <h1 class="h3 mb-3">Settings</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                @foreach ($fields as $key => $field)
                    <div class="mb-3">
                        <label class="form-label">{{ $field['label'] }}</label>

                        @if ($field['type'] === 'select')
                            <select name="{{ $key }}" class="form-select">
                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($values[$key] == $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @elseif ($field['type'] === 'boolean')
                            <div class="form-check form-switch">
                                <input type="checkbox" name="{{ $key }}" value="1" class="form-check-input"
                                       @checked($values[$key])>
                            </div>
                        @elseif ($field['type'] === 'text')
                            <textarea name="{{ $key }}" class="form-control" rows="3">{{ $values[$key] }}</textarea>
                        @else
                            <input type="text" name="{{ $key }}" value="{{ $values[$key] }}" class="form-control">
                        @endif
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
@endsection
