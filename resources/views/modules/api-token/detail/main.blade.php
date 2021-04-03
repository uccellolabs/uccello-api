@extends('uccello::modules.default.detail.main')

@section('other-blocks')
<div class="card" style="margin-bottom: 80px">
    <div class="card-content">
        {{-- Title --}}
        <div class="card-title">
            {{-- Icon --}}
            <i class="material-icons left primary-text">lock</i>

            {{-- Label --}}
            {{ uctrans('block.permissions', $module) }}
        </div>

        <div class="row">
            <div class="col s12">
                <table id="permissions-table" class="striped highlight">
                    <thead>
                        <tr>
                            <th>{{ uctrans('label.modules', $module) }}</th>
                            @foreach (['retrieve', 'create', 'update', 'delete'] as $capability)
                                <th class="center-align">{{ uctrans('capability.' . $capability, $module) }}</th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                    @foreach ($modules as $_module)
                        @continue(!Auth::user()->canAdmin($domain, $_module) || !$_module->isActiveOnDomain($domain) || !uccello()->isCrudModule($_module))
                        <tr>
                            <td>
                                <i class="material-icons left">{{ $_module->icon ?? 'extension' }}</i>
                                {{ uctrans($_module->name, $_module) }}
                            </td>

                            @foreach (['retrieve', 'create', 'update', 'delete'] as $capability)
                            <td class="center-align">
                                <p>
                                    @if (optional(optional($record->permissions)->{$_module->name})->{$capability})
                                        <i class="material-icons green-text">check</i>
                                    @else
                                        <i class="material-icons red-text">close</i>
                                    @endif
                                </p>
                            </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('uccello-extra-script')
    {{ Html::script(mix('js/profile/autoloader.js', 'vendor/uccello/uccello')) }}
@append
