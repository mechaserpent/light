<?php

namespace Light\Type;

use Exception;
use Laminas\Permissions\Rbac\Rbac;
use Light\App as LightApp;
use Light\Model\Config;
use Light\Model\User;
use Symfony\Component\Yaml\Yaml;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class App
{

    #[Field]
    public function isDevMode(#[Autowire] LightApp $app): bool
    {
        return $app->isDevMode();
    }

    #[Field]
    /**
     * @return string[]
     */
    public function getPermissions(#[Autowire] LightApp $app): array
    {
        return $app->getPermissions();
    }

    #[Field]
    #[Logged]
    /**
     * @return mixed
     */
    public function getMenus(#[InjectUser()] User $user, #[Autowire] LightApp $app)
    {
        $menus = Yaml::parseFile(dirname(__DIR__, 2) . '/menus.yml');

        $rbac = $app->getRbac();

        return $this->filterMenus($menus, $rbac, $user->getRoles());
    }

    private function filterMenus(array $menus, Rbac $rbac, array $roles)
    {
        $result = [];
        foreach ($menus as $menu) {
            if ($menu["menus"]) {
                $menu["menus"] = $this->filterMenus($menu["menus"], $rbac, $roles);


                if (!$menu["to"] && empty($menu["menus"])) {
                    continue;
                }
            }

            if ($permission = $menu["permission"]) {
                $canAccess = false;

                if (is_string($menu["permission"])) {
                    $permission = [$permission];
                }

                foreach ($permission as $p) {
                    foreach ($roles as $role) {
                        if ($rbac->isGranted($role, $p)) {
                            $canAccess = true;
                            break;
                        }
                    }
                }

                if ($canAccess) {
                    $result[] = $menu;
                }
            } else {
                $result[] = $menu;
            }
        }




        return $result;
    }

    #[Field]
    public function getCompany(): string
    {
        if ($c = Config::Get(["name" => "company"])) {
            return $c->value;
        }
        return "HostLink";
    }

    #[Field]
    public function getCompanyLogo(): ?string
    {
        if ($c = Config::Get(["name" => "company_logo"])) {
            return $c->value;
        }
    }

    #[Field]
    public function isLogged(#[InjectUser] $user): bool
    {
        if ($user) return true;
        return false;
    }


    #[Field]
    /**
     * @return Config[]
     */
    public function getConfig(): array
    {

        return Config::Query()->toArray();
    }
}