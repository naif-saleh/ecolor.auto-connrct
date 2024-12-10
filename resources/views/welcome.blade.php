@extends('layout.master')

@section('content')
<div class="container">
    @if (session('error'))
        <div class="alert alert-danger">{{(session('error'))}}</div>
    @endif
    <div class="row justify-content-center mb-4">
        <div class="col-md-8 text-center">
            <h1 class="display-4 text-primary">Welcome to Your Dashboard</h1>

            @if (Auth::check())
                <p class="lead">Hello, <strong>{{ Auth::user()->name }}</strong>! You are logged in as <strong>{{ Auth::user()->role }}</strong>.</p>
            @else
                <p class="lead">You are not logged in. <a href="{{ route('login') }}" class="btn btn-primary">Login</a> to get started.</p>
            @endif
        </div>
    </div>

    <h2 class="mb-4 text-center text-secondary">Recent Auto Distributed Files</h2>

    @if($files_dis->isEmpty())
        <div class="alert alert-warning text-center">
            <strong>No Distributed files uploaded yet.</strong>
        </div>
    @else
        <div class="row">
            @foreach($files_dis as $file)
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <img src="https://png.pngtree.com/png-clipart/20190924/original/pngtree-file-icon-for-your-project-png-image_4813854.jpg" class="card-img-top" alt="File Image">
                        <div class="card-body">
                            <h5 class="card-title">{{ $file->file_name }}</h5>
                            <p><strong>Uploaded by:</strong> {{ $file->user->name }}</p>
                            <p><strong>Uploaded on:</strong> {{ $file->created_at->format('Y-m-d') }}</p>
                            <a href="{{ route('autodistributers.show', $file->id) }}" class="btn btn-info btn-block btn-sm">View Data</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    <h2 class="mb-4 text-center text-secondary">Recent Auto Dailer Files</h2>
    @if($files_dil->isEmpty())
        <div class="alert alert-warning text-center">
            <strong>No files Dailer uploaded yet.</strong>
        </div>
    @else
        <div class="row">
            @foreach($files_dil as $file)
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <img src="https://cdn-icons-png.flaticon.com/512/2425/2425541.png" class="card-img-top" alt="File Image">
                        <div class="card-body">
                            <h5 class="card-title">{{ $file->file_name }}</h5>
                            <p><strong>Uploaded by:</strong> {{ $file->user->name }}</p>
                            <p><strong>Uploaded on:</strong> {{ $file->created_at->format('Y-m-d') }}</p>
                            <a href="{{ route('autodailers.show', $file->id) }}" class="btn btn-info btn-block btn-sm">View Data</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    <h2 class="mb-4 text-center text-secondary">Recent Providers</h2>
    @if($files_dil->isEmpty())
        <div class="alert alert-warning text-center">
            <strong>No Providers uploaded yet.</strong>
        </div>
    @else
        <div class="row">
            @foreach($providers as $provider)
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <img src="https://im.hunt.in/cg/Patna/City-Guide/internet.jpg" class="card-img-top" alt="File Image">
                        <div class="card-body">
                            <h5 class="card-title">{{ $provider->name }}</h5>
                            <p><strong>Extension:</strong> {{ $provider->extension }}</p>
                            <p><strong>Uploaded by:</strong> {{ $provider->user->name }}</p>
                            <p><strong>Uploaded on:</strong> {{ $provider->created_at->format('Y-m-d') }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
