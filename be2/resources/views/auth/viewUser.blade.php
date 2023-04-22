@extends('dashboard')
@section('content')
<main>
    <table class="table">
        <thead>
          <tr>
            <th scope="col">ID</th>
            <th scope="col">Image</th>
            <th scope="col">Name</th>
            <th scope="col">Email</th>
            <th scope="col">Phone</th>
          </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ $viewUser->id }}</td>
            <td scope="row"><img src="{{ asset ('/uploads/'. $viewUser->image) }}" alt="" width="200px"></td>
            <td>{{ $viewUser->name }}</td>
            <td>{{ $viewUser->email }}</td>
            <td>{{ $viewUser->phone }}</td>
        </tr> 
        </tbody>
      </table>
</main>
@endsection