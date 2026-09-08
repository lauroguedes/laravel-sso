<?php

use App\Models\User;

test('the root of the server is the way in, not a page about it', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('somebody already signed in is sent to their dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
