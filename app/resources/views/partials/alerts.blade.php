@if (App::environment() !== 'production')
    <div class="alert alert-warning">
        <p><i class="icon fa fa-warning"></i> This installation of OpenDominion is running on <b>{{ App::environment() }}</b> environment and is not meant for production purposes. Any data you register and actions you take on this instance might be wiped without notice.</p>
    </div>
@endif

@if (isset($selectedDominion) && $selectedDominion->isLocked())
    <div class="alert alert-warning">
        <p><i class="icon fa fa-warning"></i> This dominion is <strong>locked</strong> due to the round having ended. No actions can be performed and no ticks will be processed.</p>
        <p>Go to your <a href="{{ route('dashboard') }}">dashboard</a> to check if new rounds are open to play.</p>
    </div>
@endif

@if (!$errors->isEmpty())
    <div class="alert alert-danger alert-dismissible">
        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
        <h4>One or more errors occurred:</h4>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@foreach (['danger', 'warning', 'success', 'info'] as $alert_type)
    @if (Session::has('alert-' . $alert_type))
        <div class="alert alert-{{ $alert_type }} alert-dismissible">
            <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
            <p>{{ Session::get('alert-' . $alert_type) }}</p>
        </div>
    @endif
@endforeach
