<?php

namespace AkSearch\Controller;

use VuFind\Controller\MyResearchController as DefaultMyResearchController;
use VuFind\Exception\Auth as AuthException;

class MyResearchController extends DefaultMyResearchController {
    

    /**
     * Prepare and direct the home page where it needs to go.
     * 
     * Overriding default home action for changing followup URL
     * if OTP password authentication.
     *
     * @return mixed
     */
    public function homeAction() {
        // Process login request, if necessary (either because a form has been
        // submitted or because we're using an external login provider):
        if ($this->params()->fromPost('processLogin') || $this->getSessionInitiator() || $this->params()->fromPost('auth_method') || $this->params()->fromQuery('auth_method')) {
            try {
                if (!$this->getAuthManager()->isLoggedIn()) {
                    $this->getAuthManager()->login($this->getRequest());
                }
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }
        }

        // Not logged in?  Force user to log in:
        if (!$this->getAuthManager()->isLoggedIn()) {
            $this->setFollowupUrlToReferer();
            
            // Clear followup url so that we got to the default page after login. This is important for OTP password action.
            $clearFollowupUrl = filter_var($this->params()->fromQuery('clearFollowupUrl', false), FILTER_VALIDATE_BOOLEAN);
            if ($clearFollowupUrl) {
            	$this->clearFollowupUrl();
            }
            
            return $this->forwardTo('MyResearch', 'Login');
        }
        
        // Logged in?  Forward user to followup action
        // or default action (if no followup provided):
        if ($url = $this->getFollowupUrl()) {
            $this->clearFollowupUrl();

            // If a user clicks on the "Your Account" link, we want to be sure
            // they get to their account rather than being redirected to an old
            // followup URL. We'll use a redirect=0 GET flag to indicate this:
            if ($this->params()->fromQuery('redirect', true)) {
                return $this->redirect()->toUrl($url);
            }
        }

        $config = $this->getConfig();
        $page = isset($config->Site->defaultAccountPage) ? $config->Site->defaultAccountPage : 'Favorites';

        // Default to search history if favorites are disabled:
        if ($page == 'Favorites' && !$this->listsEnabled()) {
            return $this->forwardTo('Search', 'History');
        }
        return $this->forwardTo('MyResearch', $page);
    }

}
