@if(!empty($errors))
    <ul>
        @foreach($errors as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
