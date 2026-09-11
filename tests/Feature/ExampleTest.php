<?php

it('sends visitors to the dashboard', function () {
    $this->get('/')->assertRedirect('/pulpit');
});
