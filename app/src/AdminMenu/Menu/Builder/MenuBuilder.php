<?php

namespace App\AdminMenu\Menu\Builder;

use Aropixel\AdminBundle\Domain\Menu\Model\Link;
use Aropixel\AdminBundle\Domain\Menu\Model\SubMenu;
use Aropixel\AdminBundle\Infrastructure\Menu\Event\BuildMenuEvent;

class MenuBuilder
{

    public function __construct()
    {}

    public function buildMenu(BuildMenuEvent $event): void
    {
        $menu = $event->getMenu();

        $menu->addItem(new Link("Accueil", "_admin", [], ['icon' => "fas fa-flag"], 'menu_dashboard'));

        $menu->addItem(new Link("News", "aropixel_blog_post_index", [], ['icon' => "fas fa-newspaper"], 'menu_dashboard'));
        $menu->addItem(new Link("Pages", "aropixel_page_index", ['type' => 'default'], ['icon' => "fas fa-file-alt"], 'menu_dashboard'));



        $subMenuMenu = new SubMenu("Menus", ['icon' => "fas fa-bars"]);
        $subMenuMenu->addItem(new Link("Gérer le menu", "menu_index", ['type' => 'navbar']));
        $subMenuMenu->addItem(new Link("Gérer le footer", "menu_index", ['type' => 'footer']));
        $menu->addItem($subMenuMenu);


        $subMenu = new SubMenu("Super Administration", ['icon' => "fas fa-lock"]);
        $subMenu->addItem(new Link("Utilisateurs", "aropixel_admin_user_index", [], ['icon' => "fas fa-users"]));
        $menu->addItem($subMenu);

    }

}
