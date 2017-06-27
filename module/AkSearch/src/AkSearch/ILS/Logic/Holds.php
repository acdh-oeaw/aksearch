<?php

namespace AkSearch\ILS\Logic;
use VuFind\ILS\Logic\Holds as DefaultHolds;


class Holds extends DefaultHolds {
    

    /**
     * Extended version for Alma: We also pass an array of Holding IDs so that we save some API calls.
     * 
     * Public method for getting item holdings from the catalog and selecting which
     * holding method to call
     *
     * @param string $id  A Bib ID
     * @param array  $ids A list of Source Records (if catalog is for a consortium) or Hoding IDs (if used with Alma)
     *
     * @return array A sorted results set
     */
    public function getHoldings($id, $ids = null) {

        $holdings = [];

        // Get Holdings Data
        if ($this->catalog) {
            // Retrieve stored patron credentials; it is the responsibility of the
            // controller and view to inform the user that these credentials are
            // needed for hold data.
            $patron = $this->ilsAuth->storedCatalogLogin();

            // Does this ILS Driver handle consortial holdings?
            $config = $this->catalog->checkFunction('Holds', compact('id', 'patron'));
            if (isset($config['consortium']) && $config['consortium'] == true) {
                $result = $this->catalog->getConsortialHoldings($id, $patron ? $patron : null, $ids);
            } else {
            	$result = $this->catalog->getHolding($id, $ids, $patron ? $patron : null);
            }

            $mode = $this->catalog->getHoldsMode();

            if ($mode == "disabled") {
                $holdings = $this->standardHoldings($result);
            } else if ($mode == "driver") {
                $holdings = $this->driverHoldings($result, $config);
            } else {
                $holdings = $this->generateHoldings($result, $mode, $config);
            }

            $holdings = $this->processStorageRetrievalRequests($holdings, $id, $patron);
            $holdings = $this->processILLRequests($holdings, $id, $patron);
        }
        return $this->formatHoldings($holdings);
    }

}

