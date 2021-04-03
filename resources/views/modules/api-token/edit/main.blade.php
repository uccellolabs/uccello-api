@extends('uccello::modules.default.edit.main')

@section('other-blocks')
<div class="row">
    <div class="col s12">
        <div class="card">
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
                                    <th>
                                        <p>
                                            <label>
                                                <input type="checkbox"
                                                class="select-all filled-in" />
                                                <span class="black-text">
                                                    {{ uctrans('label.modules', $module) }}
                                                </span>
                                            </label>
                                        </p>
                                    </th>
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
                                        <p>
                                            <label>
                                                <input type="checkbox"
                                                class="filled-in select-row" />
                                                <span class="black-text">
                                                    <i class="material-icons left">{{ $_module->icon ?? 'extension' }}</i>
                                                    {{ uctrans($_module->name, $_module) }}
                                                </span>
                                            </label>
                                        </p>
                                    </td>

                                    @foreach (['retrieve', 'create', 'update', 'delete'] as $capability)
                                    <td class="center-align">
                                        <p>
                                            <label>
                                                <input type="checkbox"
                                                class="filled-in select-item"
                                                name="{{ 'permissions['.$_module->name.']['.$capability.']' }}"
                                                value="1"
                                                @if (optional(optional($record->permissions)->{$_module->name})->{$capability})checked="checked"@endif>
                                                <span></span>
                                            </label>
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
    </div>
</div>
@endsection

@section('uccello-extra-script')
    {{ Html::script(mix('js/profile/autoloader.js', 'vendor/uccello/uccello')) }}
@append
