<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Theme\ThemeNegotiator
 */
namespace Drupal\ek_admin\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

class ThemeNegotiator implements ThemeNegotiatorInterface
{

    /**
     * @param RouteMatchInterface $route_match
     * @return bool
     */
    public function applies(RouteMatchInterface $route_match)
    {
        return $this->negotiateRoute($route_match) ? true : false;
    }

    /**
     * @param RouteMatchInterface $route_match
     * @return null|string
     */
    public function determineActiveTheme(RouteMatchInterface $route_match)
    {
        return $this->negotiateRoute($route_match) ?: null;
    }

    /**
     * Function that does all of the work in selecting a theme
     * @param RouteMatchInterface $route_match
     * @return bool|string
     */
    private function negotiateRoute(RouteMatchInterface $route_match)
    {
        if ($route_match->getRouteName() == 'ek_admin.default'
                && !\Drupal::currentUser()->isAuthenticated()) {
            return 'ek_login';
        }
        

        return false;
    }
}
