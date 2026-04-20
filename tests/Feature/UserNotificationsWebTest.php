<?php

use App\Models\User;
use App\Notifications\AssignmentNotification;

test('authenticated user can list notifications via session web route', function () {
    $user = User::factory()->create();
    $user->notify(new AssignmentNotification('jobcard', 42, 'Assigned: Test job'));

    $this->actingAs($user);

    $response = $this->getJson('/user/notifications');

    $response->assertOk();
    $response->assertJsonPath('data.0.data.title', 'Assigned: Test job');
});

test('user can mark a notification read via session web route', function () {
    $user = User::factory()->create();
    $user->notify(new AssignmentNotification('task', 7, 'Task note'));

    $id = $user->notifications()->first()->id;

    $this->actingAs($user);

    $this->patchJson("/user/notifications/{$id}/read")->assertOk();

    expect($user->notifications()->first()->read_at)->not->toBeNull();
});

test('user can mark all notifications read via session web route', function () {
    $user = User::factory()->create();
    $user->notify(new AssignmentNotification('jobcard', 1, 'One'));
    $user->notify(new AssignmentNotification('jobcard', 2, 'Two'));

    $this->actingAs($user);

    $this->postJson('/user/notifications/read-all')->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});
