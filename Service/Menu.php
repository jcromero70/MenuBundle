<?php

namespace JuanCarlosRomero\MenuBundle\Service;

use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

use Symfony\Component\Yaml\Yaml;


/**
 * JuanCarlosRomero\MenuBundle\Service\Menu
 *
 */

class Menu
{

    /**
     * @var AuthorizationChecker
     */
    private $securityTokenStorage;

    /**
     * @var TokenStorage
     */
    private $securityAuthorizationChecker;

    /**
     * Array of arrays with menu definitions
     * @var array
     */
    private $menu = array();

    /**
     * Current Route
     * @var string
     */
    private $route;

    /**
     * Array with links to breadcrumbs
     * @var array
     */
    private $breadcrumbs = array();

    /**
     * Indicates whether the breadcrumb that is creating $ route matches
     * @var boolean
     */
    private $isFoundRoute = false;

    /**
     * Strin to add at end of breadcrumbs
     * @var string
     */
    private $endBreadcrumbs = '';

    /**
     * Array with new link before last breadcrumb
     * @var array
     */
    private $beforeLastBreadcrumb = array();

    /**
     * Temporal index in breadcrumbs
     * @var integer
     */
    private $indexControl = 0;

    /**
     * Identifier of the home page in the menu
     * @var array
     */
    private $homepage;

    /**
     * Constructor
     *
     * @param AuthorizationChecker $securityAuthorizationChecker
     * @param TokenStorage $securityTokenStorage
     */
    public function __construct(AuthorizationChecker $securityAuthorizationChecker,  TokenStorage $securityTokenStorage )
    {
        $this->securityAuthorizationChecker = $securityAuthorizationChecker;
        $this->securityTokenStorage = $securityTokenStorage;
    }

    /**
     * Set _route from request or specified
     *
     * @param string $route
     *
     * @return Object $this
     */
    public function setRoute($route)
    {
        $this->route = $route;

        return $this;
    }

    /**
     * Added a extra chain at the end of breadcrumbs
     *
     * @param string $endBreadcrumbs
     *
     * @return Object $this
     */
    public function setEndBreadcrumbs($endBreadcrumbs)
    {
        $this->endBreadcrumbs = $endBreadcrumbs;

        return $this;
    }

    /**
     * Added a extra chain before last breadcrumb
     *
     * @param string $name
     * @param string $route
     * @param null $icon
     *
     * @return Object $this
     */
    public function setBeforeLastBreadcrumb($name,$route,$icon = null)
    {
        $this->beforeLastBreadcrumb[] = array('name' => $name, 'route' => $route, 'icon' => $icon);

        return $this;
    }

    /**
     * Read info from file .yml
     *
     * Envia su contenido a la funcion makeMenu para que cree el menu a usar en las vistas
     *
     * @param string $file  Nombre del fichero .yml con su ruta completa
     * @param string $locale Language to use
     *
     * @return Object $this
     */
    public function setOptionsFromYaml($file)
    {
        $data = Yaml::parse($file);

        $this->makeMenu($data);

        return $this;

    }

    /**
     * return menu
     *
     * @return array
     */
    public function getMenu()
    {
        return $this->menu;
    }

    /**
     * Return breadcrumbs
     *
     * Si hay una cadena en $endBreadcrumbs, se añade al final, sino, se elimina el
     * enlace del último elemento del array.
     * Si la ruta actual no coincide con la ruta 'homepage', se añade esta al principio.
     *
     * @return array
     */
    public function getBreadcrumbs($addHomepage = true)
    {
        if (!empty($this->beforeLastBreadcrumb)){
            foreach($this->beforeLastBreadcrumb as $current)
            {
                $end = array_pop($this->breadcrumbs);
                array_push($this->breadcrumbs, array( 'title' => $current['name'], 'route' => $current['route'], 'icon' => $current['icon'] ));
                array_push($this->breadcrumbs,$end);
            }
        }
        if (!empty($this->endBreadcrumbs)){
            array_push($this->breadcrumbs, array( 'title' => $this->endBreadcrumbs, 'route' => null, 'icon' => '' ));
        } else {
            $this->breadcrumbs[count($this->breadcrumbs)-1]['route'] = null;
        }
        if($this->route != $this->homepage['route'] && $addHomepage) {
            $homepage[] = array('title' => $this->menu[$this->homepage['id_name']]['title'], 'route' => $this->menu[$this->homepage['id_name']]['route'], 'icon' => $this->menu[$this->homepage['id_name']]['icon'] );
            if ((isset($this->breadcrumbs[0])) && ($this->breadcrumbs[0]['route'] != $this->homepage['route'])){
                $this->breadcrumbs = array_merge($homepage, $this->breadcrumbs);
            }
        }

        return $this->breadcrumbs;
    }

    /**
     * Make menu
     *
     * Recorre todos su items, comprueba si hay un submenu
     * y si la ruta no ha sido encontrada, añade la actual a $breadcrumbs.
     * En caso que el item no tenga submenu, se le añade un array vacio.
     * Finalmente guarda en $menu y si el item no es la ruta reinicia $breadcrumbs
     *
     * @param array $data  Array con la información de los items del menu
     * @param string  $locale   Locale
     */
    private function makeMenu($data)
    {
        $this->homepage = $data['homepage'];

        foreach ($data['options'] as $option) {

            if (true === $this->securityAuthorizationChecker->isGranted($option['roles'], $this->securityTokenStorage->getToken()->getUser())){
                $hasSubmenu = (array_key_exists('submenu', $option));

                if (!$this->isFoundRoute) {
                    if (isset($option['route_param'])){
                        $param = $option['route_param'];
                    } else {
                        $param = null;
                    }
                    $this->makeBreadcrumbs($option['title'], $option['route'], $option['icon'], $param, $hasSubmenu);
                }

                if ($hasSubmenu) {
                    $submenu = $this->addSubMenu($option['submenu']);
                } else {
                    $submenu = array();
                }

                if (isset($option['route_param'])){
                    $param = $option['route_param'];
                } else {
                    $param = null;
                }

                if (isset($option['hidden'])){
                    $hidden = $option['hidden'];
                } else {
                    $hidden = false;
                }

                $this->menu[$option['id_name']] = array(
                    'id_name'       => $option['id_name'],
                    'title'         => $option['title'],
                    'route'         => $option['route'],
                    'roles'         => $option['roles'],
                    'route_param'   => $param,
                    'icon'          => $option['icon'],
                    'hidden'        => $hidden,
                    'submenu'       => $submenu,
                );

                if (!$this->isFoundRoute) {
                    $this->breadcrumbs = array();
                }
            }
        }

    }

    /**
     * Generates a menu submenus
     *
     * Recorre el submenu, si el item no contiene la ruta, la añade a $breadcrumbs.
     * Si el item tiene un submenu, hace una llamada recursiva a si mismo.
     * Si no se encuentra la ruta en el submenu y $indexControl es mayor que 0, borra
     * del array $breadcrumbs todos los item que el submenu haya añadido.
     *
     * @param array  $submenu   Array con la definición del submenu
     * @param string  $locale   Locale
     *
     * @return array
     */
    private function addSubMenu($submenu = array())
    {
        $temp = array();

        foreach ($submenu as $option) {
            if (true === $this->securityAuthorizationChecker->isGranted($option['roles'], $this->securityTokenStorage->getToken()->getUser())){
                $hasSubmenu = (array_key_exists('submenu', $option));

                if (!$this->isFoundRoute) {
                    if (isset($option['route_param'])){
                        $param = $option['route_param'];
                    } else {
                        $param = null;
                    }
                    $this->makeBreadcrumbs($option['title'], $option['route'], $option['icon'], $param, $hasSubmenu);
                }

                if ($hasSubmenu) {
                    $sub = $this->addSubMenu($option['submenu']);
                } else {
                    $sub = array();
                }

                if (isset($option['route_param'])){
                    $param = $option['route_param'];
                } else {
                    $param = null;
                }

                if (isset($option['hidden'])){
                    $hidden = $option['hidden'];
                } else {
                    $hidden = false;
                }

                $temp[$option['id_name']] = array(
                    'id_name'       => $option['id_name'],
                    'title'         => $option['title'],
                    'route'         => $option['route'],
                    'roles'         => $option['roles'],
                    'route_param'   => $param,
                    'icon'          => $option['icon'],
                    'hidden'        => $hidden,
                    'submenu'       => $sub,
                );
            }
        }

        if ( (!$this->isFoundRoute) && ($this->indexControl > 0) ) {
            array_splice($this->breadcrumbs, $this->indexControl + 1);
        }
        $this->indexControl = 0;

        return $temp;
    }

    /**
     * Create the array with the breadcrumbs links
     *
     * Si el item coincide con la ruta actual, pone a 'true' $isFoundRoute.
     * Si no es la ruta y tiene un submenu, añade el item a $breadcrumbs y
     * almacena en $indexControl el indice actual, por si el item y todos sus
     * posibles submenus no contienen la ruta, poder borrar los añadidos a
     * $breadcrumbs
     *
     * @param string    $title          Texto del item del menu
     * @param string    $route          Ruta del item del menu
     * @param string    $icon           Icono del item del menu
     * @param boolean   $hasSubmenu    Si el item contiene un submenu
     */
    private function makeBreadcrumbs($title, $route, $icon = '', $hasSubmenu = false)
    {
        if($this->route == $route){
            $this->isFoundRoute = true;
            array_push($this->breadcrumbs, array('title' => $title, 'icon' => $icon, 'route' => $route));
        } else {
            if ($hasSubmenu) {
                array_push($this->breadcrumbs, array('title' => $title, 'icon' => $icon,  'route' => $route));
                if ($this->indexControl == 0) $this->indexControl = count($this->breadcrumbs);
            }
        }
    }

}
