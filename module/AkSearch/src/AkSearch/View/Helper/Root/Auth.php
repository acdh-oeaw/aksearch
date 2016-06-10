<?php

namespace AkSearch\View\Helper\Root;
use Zend\View\Exception\RuntimeException;
use VuFind\View\Helper\Root\Auth as DefaultViewAuth;

class Auth extends DefaultViewAuth {

    /**
     * Render the change user data form template.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getChangeUserDataForm($context = []) {
        return $this->renderTemplate('changeuserdata.phtml', $context);
    }
    

}
