@extends('layouts.master')

@section('content')

<div id="users" data-role="page" data-theme="{{ $theme_body }}" data-divider-theme="{{ $theme_bars }}" data-transition="{{ $default_page_transition }}" data-url="/admin/users" >

	@include('partials.header')
	
	<div data-role="content">

		<div class="padding-bottom-fifteen-pixels"></div>
		
		<ul id="usersList" data-role="listview" data-filter="true" data-inset="true" data-theme="{{ $theme_buttons }}" data-divider-theme="{{ $theme_bars }}"> 

			<li data-role="list-divider">{{ $users_i18n }}</li>

                        	@foreach($users as $user)

                                	@include('partials.user')

                        	@endforeach

			<li data-role="list-divider"></li>
		</ul>
	</div>

	@include('partials.footer')

</div>

@stop
