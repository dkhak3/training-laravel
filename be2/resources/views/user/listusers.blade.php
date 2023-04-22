@extends('dashboard')
@section('content')
    <main>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Id</th>
                    <th scope="col">Image</th>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Password</th>
                    <th scope="col">Created_at</th>
                    <th scope="col">Updated_at</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($listUsers as $item)
                    <tr>
                        <th scope="row">{{ $item['id'] }}</th>
                        <td><img width="100px" src="{{ asset ('/uploads/'. $item['image']) }}" alt=""></td>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['email'] }}</td>
                        <td>{{ $item['phone'] }}</td>
                        <td class="overflow-x-hidden">{{ $item['password'] }}</td>
                        <td>{{ $item['created_at'] }}</td>
                        <td>{{ $item['updated_at'] }}</td>
                        <td>
                            <button class="btn btn-warning">Update</button>
                            <button class="btn btn-danger">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pagination-page">
            <nav aria-label="Page navigation example">
                <ul class="pagination">
                    @if ($pageNow > 1)
                        <li class="page-item"><a class="page-link"
                                href="{{ route('listusers', $pageNow - 1) }}">Previous</a></li>
                    @endif

                    @if ($totalPage != 1)
                        @for ($i = 1; $i <= $totalPage; $i++)
                            <li class="page-item"><a class="page-link"
                                    href="{{ route('listusers', $i) }}">{{ $i }}</a></li>
                        @endfor
                    @endif

                    @if ($pageNow < $totalPage)
                        <li class="page-item"><a class="page-link" href="{{ route('listusers', $pageNow + 1) }}">Next</a>
                        </li>
                    @endif
                </ul>
            </nav>
        </div>
    </main>
@endsection
