<?php

namespace AkSearch\RecordTab;


class Description extends \VuFind\RecordTab\Description {
    /**
     * 
     * {@inheritDoc}
     * AKsearch: Rename this tab to "Details"
     * 
     * @return string
     */
    public function getDescription() {
        return 'Details';
    }
}