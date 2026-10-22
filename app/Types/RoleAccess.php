<?php
namespace App\Types;

class RoleAccess
{
    public $subject;
    public $actions;

    public function __construct($subject)
    {
        $this->subject = $subject;
        $this->actions = [];
    }

    public function addAction($action)
    {
        $this->actions[] = $action;
    }
}
