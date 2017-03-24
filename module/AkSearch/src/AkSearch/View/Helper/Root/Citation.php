<?php

namespace AkSearch\View\Helper\Root;
use VuFind\Exception\Date as DateException;


class Citation extends \VuFind\View\Helper\Root\Citation {
    /**
     * Citation details
     *
     * @var array
     */
    protected $details = [];

    /**
     * Record driver
     *
     * @var \VuFind\RecordDriver\AbstractBase
     */
    protected $driver;

    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;


    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $converter Date converter
     */
    public function __construct(\VuFind\Date\Converter $converter) {
        $this->dateConverter = $converter;
    }

    
    /**
     * Store a record driver object and return this object so that the appropriate
     * template can be rendered.
     *
     * @param \VuFind\RecordDriver\Base $driver Record driver object.
     *
     * @return Citation
     */
    public function __invoke($driver) {
        // Build author list:
        $authors = [];
        $primary = $driver->tryMethod('getPrimaryAuthor', [false]);
        if (empty($primary)) {
            $primary = $driver->tryMethod('getCorporateAuthor');
        }
        if (!empty($primary)) {
            $authors[] = $primary;
        }
        $secondary = $driver->tryMethod('getSecondaryAuthors');
        if (is_array($secondary) && !empty($secondary)) {
            $authors = array_unique(array_merge($authors, $secondary));
        }
        $additional = $driver->tryMethod('getAdditionalAuthors');
        if (is_array($additional) && !empty($additional)) {
        	$authors = array_unique(array_merge($authors, $additional));
        }

        // Get best available title details:
        $title = $driver->tryMethod('getShortTitle');
        $subtitle = $driver->tryMethod('getSubtitle');
        if (empty($title)) {
            $title = $driver->tryMethod('getTitle');
        }
        if (empty($title)) {
            $title = $driver->getBreadcrumb();
        }
        // Find subtitle in title if they're not separated:
        if (empty($subtitle) && strstr($title, ':')) {
            list($title, $subtitle) = explode(':', $title, 2);
        }

        // Extract the additional details from the record driver:
        $publishers = $driver->tryMethod('getPublishers');
        $pubDates = $driver->tryMethod('getPublicationDates');
        $pubPlaces = $driver->tryMethod('getPlacesOfPublication');
        $edition = $driver->tryMethod('getEdition');

        // Store everything:
        $this->driver = $driver;
        $this->details = [
            'authors' => $this->prepareAuthors($authors),
            'title' => trim($title), 'subtitle' => trim($subtitle),
            'pubPlace' => isset($pubPlaces[0]) ? $pubPlaces[0] : null,
            'pubName' => isset($publishers[0]) ? $publishers[0] : null,
            'pubDate' => isset($pubDates[0]) ? $pubDates[0] : null,
            'edition' => empty($edition) ? [] : [$edition],
            'journal' => $driver->tryMethod('getArticleParentTitle')
        ];

        return $this;
    }


    /**
     * Get APA citation.
     *
     * This function assigns all the necessary variables and then returns an APA
     * citation.
     *
     * @return string
     */
    public function getCitationAPA() {
        $apa = [
            'title' => $this->getAPATitle(),
            'authors' => $this->getAPAAuthors(),
            'edition' => $this->getEdition(),
        	'volume' => $this->getVolume(),
        	'issue' => $this->getIssue()
        ];
        
        // Show a period after the title if it does not already have punctuation
        // and is not followed by an edition statement:
        $apa['periodAfterTitle'] = (!$this->isPunctuated($apa['title']) && empty($apa['edition']));

        // Behave differently for books vs. journals:
        $partial = $this->getView()->plugin('partial');
        if (empty($this->details['journal'])) {
            $apa['publisher'] = $this->getPublisher();
            $apa['year'] = $this->getYear();
            return $partial('Citation/apa.phtml', $apa);
        } else {
            list($apa['volume'], $apa['issue'], $apa['date']) = $this->getAPANumbersAndDate();
            $apa['journal'] = $this->details['journal'];
            $apa['pageRange'] = $this->getPageRange();
            if ($doi = $this->driver->tryMethod('getCleanDOI')) {
                $apa['doi'] = $doi;
            }
            return $partial('Citation/apa-article.phtml', $apa);
        }
    }
    
    
    /**
     * Get volume number
     * 
     * @return string|mixed|NULL
     */
    protected function getVolume() {
    	$volumeNo = $this->driver->tryMethod('getArticleParentVolumeNo');
    	return ($volumeNo != null) ? $volumeNo : '';
    }
    
    
    /**
     * Get issue number
     * 
     * @return string|mixed|NULL
     */
    protected function getIssue() {
    	$issueNo = $this->driver->tryMethod('getArticleParentIssueNo');
    	return ($issueNo != null) ? $issueNo : '';
    }


    /**
     * Get MLA citation.
     *
     * This function assigns all the necessary variables and then returns an MLA
     * citation. By adjusting the parameters below, it can also render a Chicago
     * Style citation.
     *
     * @param int    $etAlThreshold   The number of authors to abbreviate with 'et
     * al.'
     * @param string $volNumSeparator String to separate volume and issue number
     * in citation.
     *
     * @return string
     */
    public function getCitationMLA($etAlThreshold = 3, $volNumSeparator = '.') {    	
        $mla = [
            'title' => $this->getMLATitle(),
            'authors' => $this->getMLAAuthors($etAlThreshold)
        ];
        $mla['periodAfterTitle'] = !$this->isPunctuated($mla['title']);

        // Behave differently for books vs. journals:
        $partial = $this->getView()->plugin('partial');
        if (empty($this->details['journal'])) {
            $mla['publisher'] = $this->getPublisher();
            $mla['year'] = $this->getYear();
            $mla['edition'] = $this->getEdition();
            return $partial('Citation/mla.phtml', $mla);
        } else {
            // Add other journal-specific details:
            $mla['pageRange'] = $this->getPageRange();
            $mla['journal'] =  $this->capitalizeTitle($this->details['journal']);
            $mla['numberAndDate'] = $this->getMLANumberAndDate($volNumSeparator);
            return $partial('Citation/mla-article.phtml', $mla);
        }
    }

    
    /**
     * Construct page range portion of citation.
     *
     * @return string
     */
    protected function getPageRange() {
        $pages = $this->driver->tryMethod('getArticlePages');        
        return ($pages != null && trim($pages) != '-') ? $pages : 'n. A';
    }

    
    /**
     * Construct volume/issue/date portion of MLA or Chicago Style citation.
     *
     * @param string $volNumSeparator String to separate volume and issue number
     * in citation (only difference between MLA/Chicago Style).
     *
     * @return string
     */
    protected function getMLANumberAndDate($volNumSeparator = '.') {
        $vol = $this->driver->tryMethod('getArticleParentVolumeNo');
        $num = $this->driver->tryMethod('getArticleParentIssueNo');
        $date = $this->details['pubDate'];
        if (strlen($date) > 4) {
            try {
                $year = $this->dateConverter->convertFromDisplayDate('Y', $date);
                $month = $this->dateConverter->convertFromDisplayDate('M', $date)
                    . '.';
                $day = $this->dateConverter->convertFromDisplayDate('j', $date);
            } catch (DateException $e) {
                // If conversion fails, use raw date as year -- not ideal,
                // but probably better than nothing:
                $year = $date;
                $month = $day = '';
            }
        } else {
            $year = $date;
            $month = $day = '';
        }

        // We need to supply additional date information if no vol/num:
        if (!empty($vol) || !empty($num)) {
            // If volume and number are both non-empty, separate them with a
            // period; otherwise just use the one that is set.
            $volNum = (!empty($vol) && !empty($num))
                ? $vol . $volNumSeparator . $num : $vol . $num;
            return $volNum . ' (' . $year . ')';
        } else {
            // Right now, we'll assume if day == 1, this is a monthly publication;
            // that's probably going to result in some bad citations, but it's the
            // best we can do without writing extra record driver methods.
            return (($day > 1) ? $day . ' ' : '')
                . (empty($month) ? '' : $month . ' ')
                . $year;
        }
    }

    
    /**
     * Construct volume/issue/date portion of APA citation.  Returns an array with
     * three elements: volume, issue and date (since these end up in different areas
     * of the final citation, we don't return a single string, but since their
     * determination is related, we need to do the work in a single function).
     *
     * @return array
     */
    protected function getAPANumbersAndDate() {
        $vol = $this->driver->tryMethod('getArticleParentVolumeNo');
        $num = $this->driver->tryMethod('getArticleParentIssueNo');
        $date = $this->details['pubDate'];
        if (strlen($date) > 4) {
            try {
                $year = $this->dateConverter->convertFromDisplayDate('Y', $date);
                $month = $this->dateConverter->convertFromDisplayDate('F', $date);
                $day = $this->dateConverter->convertFromDisplayDate('j', $date);
            } catch (DateException $e) {
                // If conversion fails, use raw date as year -- not ideal,
                // but probably better than nothing:
                $year = $date;
                $month = $day = '';
            }
        } else {
            $year = $date;
            $month = $day = '';
        }

        // We need to supply additional date information if no vol/num:
        if (!empty($vol) || !empty($num)) {
            // If only the number is non-empty, move the value to the volume to
            // simplify template behavior:
            if (empty($vol) && !empty($num)) {
                $vol = $num;
                $num = '';
            }
            return [$vol, $num, $year];
        } else {
            // Right now, we'll assume if day == 1, this is a monthly publication;
            // that's probably going to result in some bad citations, but it's the
            // best we can do without writing extra record driver methods.
            $finalDate = $year
                . (empty($month) ? '' : ', ' . $month)
                . (($day > 1) ? ' ' . $day : '');
            return ['', '', $finalDate];
        }
    }


    /**
     * Get the full title for an APA citation.
     *
     * @return string
     */
    protected function getAPATitle() {
        // Create Title
        $title = $this->stripPunctuation($this->details['title']);
        $title = str_replace(array('<', '>'), '', $title);
        if (isset($this->details['subtitle'])) {
            $subtitle = $this->stripPunctuation($this->details['subtitle']);
            $subtitle = str_replace(array('<', '>'), '', $subtitle);
            // Capitalize subtitle and apply it, assuming it really exists:
            if (!empty($subtitle)) {
                $subtitle = strtoupper(substr($subtitle, 0, 1)) . substr($subtitle, 1);
                $title .= ': ' . $subtitle;
            }
        }

        return $title;
    }
}