@extends('layout.main')
@section('title', 'Dialer | mobiles of ' . $file->file_name)
@section('content')
    <div class="container">
        <div class="d-flex justify-content-between">
            <h2>File Name: {{ $file->file_name }} contains <u>{{ $numbers }}</u> Numbers - Called on <u>{{$called}}</u></h2>
            <a href="{{ route('provider.files.index', $file->provider_id) }}" class="btn btn-primary mb-2">Back</a>
        </div>

        @if (!empty($file))
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Mobile Number</th>
                        <th>state</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $index as $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $row->mobile ?? 'N/A' }}</td>
                            <td>{{ $row->state ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No data found in this file.</p>
        @endif
        <div class="pagination-wrapper d-flex justify-content-center mt-4">
            <ul class="pagination">
                <li class="page-item {{ $data->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $data->previousPageUrl() }}" tabindex="-1" aria-disabled="true">Previous</a>
                </li>
                @foreach ($data->getUrlRange(1, $data->lastPage()) as $page => $url)
                    <li class="page-item {{ $data->currentPage() == $page ? 'active' : '' }}">
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    </li>
                @endforeach
                <li class="page-item {{ $data->hasMorePages() ? '' : 'disabled' }}">
                    <a class="page-link" href="{{ $data->nextPageUrl() }}">Next</a>
                </li>
            </ul>
        </div>

    </div>
@endsection
