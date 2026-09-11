<?php

use App\Domain\Team\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->trainer = User::factory()->create();
});

test('only the owner runs the team: the list, invitations and password resets', function () {
    expect(Gate::forUser($this->owner)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('create', User::class))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('resetPassword', $this->trainer))->toBeTrue()
        ->and(Gate::forUser($this->trainer)->allows('viewAny', User::class))->toBeFalse()
        ->and(Gate::forUser($this->trainer)->allows('create', User::class))->toBeFalse()
        ->and(Gate::forUser($this->trainer)->allows('resetPassword', $this->owner))->toBeFalse();
});

test('everyone keeps their own card, nobody else\'s', function () {
    $other = User::factory()->create();

    expect(Gate::forUser($this->trainer)->allows('update', $this->trainer))->toBeTrue()
        ->and(Gate::forUser($this->trainer)->allows('update', $other))->toBeFalse()
        ->and(Gate::forUser($this->owner)->allows('update', $this->trainer))->toBeTrue();
});

test('the owner account cannot be blocked, a trainer can', function () {
    expect(Gate::forUser($this->owner)->allows('block', $this->trainer))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('block', $this->owner))->toBeFalse()
        ->and(Gate::forUser($this->trainer)->allows('block', $this->owner))->toBeFalse();
});

test('the studio rules are the owner\'s to change and everyone else\'s to read', function () {
    expect(Gate::forUser($this->owner)->allows('manage-studio-rules'))->toBeTrue()
        ->and(Gate::forUser($this->trainer)->allows('manage-studio-rules'))->toBeFalse();
});

test('an invited or blocked account holds no rights at all', function () {
    $invited = User::factory()->invited()->create();
    $blocked = User::factory()->blocked()->create();

    expect(Gate::forUser($invited)->allows('update', $invited))->toBeFalse()
        ->and(Gate::forUser($blocked)->allows('update', $blocked))->toBeFalse()
        ->and(Gate::forUser($blocked)->allows('manage-studio-rules'))->toBeFalse();
});
