<?php

namespace App\Traits;

use App\Types\RoleAccess;

trait RoleTrait
{
    function transformRoles($roles)
    {
        $transformedRoles = [];
        foreach ($roles as $role) {
            $access = [];
            foreach ($role->permissions as $permission) {
                $subject = $permission->subject;
                $action = $permission->action;
                $existingSubject = null;
                foreach ($access as $item) {
                    if ($item->subject === $subject) {
                        $existingSubject = $item;
                        break;
                    }
                }
                if (!$existingSubject) {
                    $existingSubject = new RoleAccess($subject);
                    $access[] = $existingSubject;
                }
                $existingSubject->addAction($action);
            }
            $transformedRoles[] = [
                'id' => $role->id,
                'name' => $role->name,
                'icon' => $role->icon,
                'description' => $role->description,
                'subject' => $role->subject,
                'access' => $access,
            ];
        }

        return $transformedRoles;
    }
    function transformRole($role)
    {
        $access = [];
        foreach ($role->permissions as $permission) {
            $subject = $permission->subject;
            $action = $permission->action;
            $existingSubject = null;
            foreach ($access as $item) {
                if ($item->subject === $subject) {
                    $existingSubject = $item;
                    break;
                }
            }
            if (!$existingSubject) {
                $existingSubject = new RoleAccess($subject);
                $access[] = $existingSubject;
            }
            $existingSubject->addAction($action);
        }
        return [
            'id' => $role->id,
            'name' => $role->name,
            'icon' => $role->icon,
            'description' => $role->description,
            'subject' => $role->subject,
            'access' => $access,
        ];
    }
}
