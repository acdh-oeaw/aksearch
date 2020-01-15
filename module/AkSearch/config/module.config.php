<?php
/**
 * Configuration for AkSearch Module.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1335, USA.
 *
 * @category AkSearch
 * @package  Module
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */
 
namespace AkSearch\Module\Config;

/*
// Show PHP errors:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(- 1);
*/

$config = [
    'router' => [
        'routes' => [
            'default' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/[:controller[/[:action]]]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'index',
                        'action'     => 'Home',
                    ],
                ],
            ],
            'legacy-alphabrowse-results' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/AlphaBrowse/Results',
                    'defaults' => [
                        'controller' => 'Alphabrowse',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'legacy-bookcover' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/bookcover.php',
                    'defaults' => [
                        'controller' => 'cover',
                        'action'     => 'Show',
                    ]
                ]
            ],
            'legacy-summonrecord' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/Summon/Record',
                    'defaults' => [
                        'controller' => 'SummonRecord',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'legacy-worldcatrecord' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/WorldCat/Record',
                    'defaults' => [
                        'controller' => 'WorldcatRecord',
                        'action'     => 'Home',
                    ]
                ]
            ],
        	'api-user' => [
        		'type'    => 'Zend\Mvc\Router\Http\Segment',
        		'options' => [
        			'route'    => '/Api/User/[:apiUserAction]',
        			//'route'    => '/Api/User',
        			'constraints' => [
        				'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
        				'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        			],
        			'defaults' => [
        				'controller' => 'Api',
        				'action' => 'User',
        			],
        		],
        	],
        	'api-webhook' => [
        		'type'    => 'Zend\Mvc\Router\Http\Segment',
        		'options' => [
        			'route'    => '/Api/Webhook/[:apiWebhookAction]',
        			//'route'    => '/Api/Webhook',
        			'constraints' => [
        				'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
        				'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        			],
        			'defaults' => [
        				'controller' => 'Api',
        				'action' => 'Webhook',
        			],
        		],
        	],
        ],
    ],
    'controllers' => [
        'factories' => [
        	'aksites' => 'AkSearch\Controller\Factory::getAkSitesController',
            'api' => 'AkSearch\Controller\Factory::getApiController',
        	//'browse' => 'VuFind\Controller\Factory::getBrowseController',
        	'browse' => 'AkSearch\Controller\Factory::getBrowseController',
            'collection' => 'VuFind\Controller\Factory::getCollectionController',
            'collections' => 'VuFind\Controller\Factory::getCollectionsController',
            'record' => 'VuFind\Controller\Factory::getRecordController',
            'upgrade' => 'VuFind\Controller\Factory::getUpgradeController',
        ],
        'invokables' => [
        	'akfilter' => 'AkSearch\Controller\AkfilterController',
        	'ajax' => 'AkSearch\Controller\AkAjaxController',
            //'ajax' => 'VuFind\Controller\AjaxController',
            'alphabrowse' => 'VuFind\Controller\AlphabrowseController',
            'author' => 'VuFind\Controller\AuthorController',
            'authority' => 'VuFind\Controller\AuthorityController',
            'cart' => 'VuFind\Controller\CartController',
            'combined' => 'VuFind\Controller\CombinedController',
            'confirm' => 'VuFind\Controller\ConfirmController',
            'cover' => 'VuFind\Controller\CoverController',
            'eds' => 'VuFind\Controller\EdsController',
            'edsrecord' => 'VuFind\Controller\EdsrecordController',
            'eit' => 'VuFind\Controller\EITController',
            'eitrecord' => '\VuFind\Controller\EITrecordController',
            'error' => 'VuFind\Controller\ErrorController',
            'feedback' => 'VuFind\Controller\FeedbackController',
            'help' => 'VuFind\Controller\HelpController',
            'hierarchy' => 'VuFind\Controller\HierarchyController',
            'index' => 'VuFind\Controller\IndexController',
            'install' => 'VuFind\Controller\InstallController',
            'libguides' => 'VuFind\Controller\LibGuidesController',
            'librarycards' => 'VuFind\Controller\LibraryCardsController',
            'missingrecord' => 'VuFind\Controller\MissingrecordController',
            //'my-research' => 'VuFind\Controller\MyResearchController',
        	'myresearch' => 'AkSearch\Controller\MyResearchController',
            'oai' => 'VuFind\Controller\OaiController',
            'pazpar2' => 'VuFind\Controller\Pazpar2Controller',
            'primo' => 'VuFind\Controller\PrimoController',
            'primorecord' => 'VuFind\Controller\PrimorecordController',
            'qrcode' => 'VuFind\Controller\QRCodeController',
            'records' => 'VuFind\Controller\RecordsController',
            //'search' => 'VuFind\Controller\SearchController',
        	'search' => 'AkSearch\Controller\SearchController',
            'summon' => 'VuFind\Controller\SummonController',
            'summonrecord' => 'VuFind\Controller\SummonrecordController',
            'tag' => 'VuFind\Controller\TagController',
            'web' => 'VuFind\Controller\WebController',
            'worldcat' => 'VuFind\Controller\WorldcatController',
            'worldcatrecord' => 'VuFind\Controller\WorldcatrecordController',
        ],
        'initializers' => [
            'ZfcRbac\Initializer\AuthorizationServiceInitializer'
        ],
    ],
    'controller_plugins' => [
        'factories' => [
        	'aksearch' => 'AkSearch\Controller\Plugin\Factory::getAkSearch',
            'holds' => 'VuFind\Controller\Plugin\Factory::getHolds',
            //'newitems' => 'VuFind\Controller\Plugin\Factory::getNewItems',
        	'newitems' => 'AkSearch\Controller\Plugin\Factory::getNewItems',
            'ILLRequests' => 'VuFind\Controller\Plugin\Factory::getILLRequests',
            'recaptcha' => 'VuFind\Controller\Plugin\Factory::getRecaptcha',
            'reserves' => 'VuFind\Controller\Plugin\Factory::getReserves',
            'storageRetrievalRequests' => 'VuFind\Controller\Plugin\Factory::getStorageRetrievalRequests',
        ],
        'invokables' => [
            'db-upgrade' => 'VuFind\Controller\Plugin\DbUpgrade',
            'favorites' => 'VuFind\Controller\Plugin\Favorites',
            'followup' => 'VuFind\Controller\Plugin\Followup',
            'renewals' => 'VuFind\Controller\Plugin\Renewals',
            'result-scroller' => 'VuFind\Controller\Plugin\ResultScroller',
        ]
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            //'VuFind\AuthManager' => 'VuFind\Auth\Factory::getManager',
        	'VuFind\AuthManager' => 'AkSearch\Auth\Factory::getManager',
            'VuFind\AuthPluginManager' => 'VuFind\Service\Factory::getAuthPluginManager',
            'VuFind\AutocompletePluginManager' => 'VuFind\Service\Factory::getAutocompletePluginManager',
            'VuFind\CacheManager' => 'VuFind\Service\Factory::getCacheManager',
            'VuFind\Cart' => 'VuFind\Service\Factory::getCart',
            'VuFind\Config' => 'VuFind\Service\Factory::getConfig',
            'VuFind\ContentPluginManager' => 'VuFind\Service\Factory::getContentPluginManager',
            'VuFind\ContentAuthorNotesPluginManager' => 'VuFind\Service\Factory::getContentAuthorNotesPluginManager',
            'VuFind\ContentCoversPluginManager' => 'VuFind\Service\Factory::getContentCoversPluginManager',
            'VuFind\ContentExcerptsPluginManager' => 'VuFind\Service\Factory::getContentExcerptsPluginManager',
            'VuFind\ContentReviewsPluginManager' => 'VuFind\Service\Factory::getContentReviewsPluginManager',
            'VuFind\CookieManager' => 'VuFind\Service\Factory::getCookieManager',
            'VuFind\DateConverter' => 'VuFind\Service\Factory::getDateConverter',
            'VuFind\DbAdapter' => 'VuFind\Service\Factory::getDbAdapter',
            'VuFind\DbAdapterFactory' => 'VuFind\Service\Factory::getDbAdapterFactory',
            'VuFind\DbTablePluginManager' => 'VuFind\Service\Factory::getDbTablePluginManager',
            'VuFind\Export' => 'VuFind\Service\Factory::getExport',
            'VuFind\HierarchyDriverPluginManager' => 'VuFind\Service\Factory::getHierarchyDriverPluginManager',
            'VuFind\HierarchyTreeDataFormatterPluginManager' => 'VuFind\Service\Factory::getHierarchyTreeDataFormatterPluginManager',
            'VuFind\HierarchyTreeDataSourcePluginManager' => 'VuFind\Service\Factory::getHierarchyTreeDataSourcePluginManager',
            'VuFind\HierarchyTreeRendererPluginManager' => 'VuFind\Service\Factory::getHierarchyTreeRendererPluginManager',
            'VuFind\Http' => 'VuFind\Service\Factory::getHttp',
            'VuFind\HMAC' => 'VuFind\Service\Factory::getHMAC',
            'VuFind\ILSAuthenticator' => 'VuFind\Auth\Factory::getILSAuthenticator',
            'VuFind\ILSConnection' => 'VuFind\Service\Factory::getILSConnection',
            'VuFind\ILSDriverPluginManager' => 'VuFind\Service\Factory::getILSDriverPluginManager',
            //'VuFind\ILSHoldLogic' => 'VuFind\Service\Factory::getILSHoldLogic',
        	'VuFind\ILSHoldLogic' => 'AkSearch\Service\Factory::getILSHoldLogic',
            'VuFind\ILSHoldSettings' => 'VuFind\Service\Factory::getILSHoldSettings',
            'VuFind\ILSTitleHoldLogic' => 'VuFind\Service\Factory::getILSTitleHoldLogic',
            'VuFind\Logger' => 'VuFind\Service\Factory::getLogger',
            'VuFind\Mailer' => 'VuFind\Mailer\Factory',
            'VuFind\ProxyConfig' => 'VuFind\Service\Factory::getProxyConfig',
            'VuFind\Recaptcha' => 'VuFind\Service\Factory::getRecaptcha',
            'VuFind\RecommendPluginManager' => 'VuFind\Service\Factory::getRecommendPluginManager',
            'VuFind\RecordDriverPluginManager' => 'VuFind\Service\Factory::getRecordDriverPluginManager',
            'VuFind\RecordLoader' => 'VuFind\Service\Factory::getRecordLoader',
            'VuFind\RecordRouter' => 'VuFind\Service\Factory::getRecordRouter',
            'VuFind\RecordStats' => 'VuFind\Service\Factory::getRecordStats',
            'VuFind\RecordTabPluginManager' => 'VuFind\Service\Factory::getRecordTabPluginManager',
            'VuFind\RelatedPluginManager' => 'VuFind\Service\Factory::getRelatedPluginManager',
            'VuFind\ResolverDriverPluginManager' => 'VuFind\Service\Factory::getResolverDriverPluginManager',
            'VuFind\Search\BackendManager' => 'VuFind\Service\Factory::getSearchBackendManager',
            'VuFind\SearchOptionsPluginManager' => 'VuFind\Service\Factory::getSearchOptionsPluginManager',
            'VuFind\SearchParamsPluginManager' => 'VuFind\Service\Factory::getSearchParamsPluginManager',
            'VuFind\SearchResultsPluginManager' => 'VuFind\Service\Factory::getSearchResultsPluginManager',
            'VuFind\SearchRunner' => 'VuFind\Service\Factory::getSearchRunner',
            'VuFind\SearchSpecsReader' => 'VuFind\Service\Factory::getSearchSpecsReader',
            'VuFind\SearchStats' => 'VuFind\Service\Factory::getSearchStats',
            'VuFind\SessionManager' => 'VuFind\Service\Factory::getSessionManager',
            'VuFind\SessionPluginManager' => 'VuFind\Service\Factory::getSessionPluginManager',
            'VuFind\SMS' => 'VuFind\SMS\Factory',
            'VuFind\Solr\Writer' => 'VuFind\Service\Factory::getSolrWriter',
            'VuFind\StatisticsDriverPluginManager' => 'VuFind\Service\Factory::getStatisticsDriverPluginManager',
            'VuFind\Tags' => 'VuFind\Service\Factory::getTags',
            'VuFind\Translator' => 'VuFind\Service\Factory::getTranslator',
            'VuFind\WorldCatUtils' => 'VuFind\Service\Factory::getWorldCatUtils',
        ],
        'invokables' => [
            'VuFind\Search'         => 'VuFindSearch\Service',
            'VuFind\Search\Memory'  => 'VuFind\Search\Memory',
            'VuFind\HierarchicalFacetHelper' => 'VuFind\Search\Solr\HierarchicalFacetHelper'
        ],
        'initializers' => [
            'VuFind\ServiceManager\Initializer::initInstance',
        ],
        'aliases' => [
            'mvctranslator' => 'VuFind\Translator',
            'translator' => 'VuFind\Translator',
        ],
    ],
    'translator' => [],
    'view_helpers' => [
        'initializers' => [
            'VuFind\ServiceManager\Initializer::initZendPlugin',
        	//'ZfcRbac\Initializer\AuthorizationServiceInitializer', // Added by AK Bibliothek Wien - Search box permission
        ],
    ],
    'view_manager' => [
        'display_not_found_reason' => APPLICATION_ENV == 'development',
        'display_exceptions'       => APPLICATION_ENV == 'development',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_path_stack'      => [],
    ],
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific Zend Framework 2 components).
    'vufind' => [
        // The config reader is a special service manager for loading .ini files:
        'config_reader' => [
            'abstract_factories' => ['VuFind\Config\PluginFactory'],
        ],
        // PostgreSQL sequence mapping
        'pgsql_seq_mapping'  => [
            'comments'       => ['id', 'comments_id_seq'],
            'oai_resumption' => ['id', 'oai_resumption_id_seq'],
            'resource'       => ['id', 'resource_id_seq'],
            'resource_tags'  => ['id', 'resource_tags_id_seq'],
            'search'         => ['id', 'search_id_seq'],
            'session'        => ['id', 'session_id_seq'],
            'tags'           => ['id', 'tags_id_seq'],
            'user'           => ['id', 'user_id_seq'],
            'user_list'      => ['id', 'user_list_id_seq'],
            'user_resource'  => ['id', 'user_resource_id_seq']
        ],
        // This section contains service manager configurations for all VuFind
        // pluggable components:
        'plugin_managers' => [
            'auth' => [
                'abstract_factories' => [
                		'VuFind\Auth\PluginFactory',
                ],
                'factories' => [
                    //'ils' => 'VuFind\Auth\Factory::getILS',
                	'ils' => 'AkSearch\Auth\Factory::getILS',
                    'multiils' => 'VuFind\Auth\Factory::getMultiILS',                	
                ],
                'invokables' => [
                    'cas' => 'VuFind\Auth\CAS',
                    'choiceauth' => 'VuFind\Auth\ChoiceAuth',
                    //'database' => 'VuFind\Auth\Database',
                	'database' => 'AkSearch\Auth\Database',
                    'facebook' => 'VuFind\Auth\Facebook',
                    'ldap' => 'VuFind\Auth\LDAP',
                    'multiauth' => 'VuFind\Auth\MultiAuth',
                    'shibboleth' => 'VuFind\Auth\Shibboleth',
                    'sip2' => 'VuFind\Auth\SIP2',
                ],
                'aliases' => [
                    // for legacy 1.x compatibility
                    'db' => 'Database',
                    'sip' => 'Sip2',
                ],
            ],
            'autocomplete' => [
                'abstract_factories' => ['VuFind\Autocomplete\PluginFactory'],
                'factories' => [
                    'solr' => 'VuFind\Autocomplete\Factory::getSolr',
                    'solrauth' => 'VuFind\Autocomplete\Factory::getSolrAuth',
                    'solrcn' => 'VuFind\Autocomplete\Factory::getSolrCN',
                    'solrreserves' => 'VuFind\Autocomplete\Factory::getSolrReserves',
                ],
                'invokables' => [
                    'none' => 'VuFind\Autocomplete\None',
                    'oclcidentities' => 'VuFind\Autocomplete\OCLCIdentities',
                    'tag' => 'VuFind\Autocomplete\Tag',
                ],
                'aliases' => [
                    // for legacy 1.x compatibility
                    'noautocomplete' => 'None',
                    'oclcidentitiesautocomplete' => 'OCLCIdentities',
                    'solrautocomplete' => 'Solr',
                    'solrauthautocomplete' => 'SolrAuth',
                    'solrcnautocomplete' => 'SolrCN',
                    'solrreservesautocomplete' => 'SolrReserves',
                    'tagautocomplete' => 'Tag',
                ],
            ],
            'content' => [
                'factories' => [
                    'authornotes' => 'VuFind\Content\Factory::getAuthorNotes',
                    'excerpts' => 'VuFind\Content\Factory::getExcerpts',
                    'reviews' => 'VuFind\Content\Factory::getReviews',
                ],
            ],
            'content_authornotes' => [
                'factories' => [
                    'syndetics' => 'VuFind\Content\AuthorNotes\Factory::getSyndetics',
                    'syndeticsplus' => 'VuFind\Content\AuthorNotes\Factory::getSyndeticsPlus',
                ],
            ],
            'content_excerpts' => [
                'factories' => [
                    'syndetics' => 'VuFind\Content\Excerpts\Factory::getSyndetics',
                    'syndeticsplus' => 'VuFind\Content\Excerpts\Factory::getSyndeticsPlus',
                ],
            ],
            'content_covers' => [
                'factories' => [
                    'amazon' => 'VuFind\Content\Covers\Factory::getAmazon',
                    'booksite' => 'VuFind\Content\Covers\Factory::getBooksite',
                    'contentcafe' => 'VuFind\Content\Covers\Factory::getContentCafe',
                    'syndetics' => 'VuFind\Content\Covers\Factory::getSyndetics',
                ],
                'invokables' => [
                    'google' => 'VuFind\Content\Covers\Google',
                    'librarything' => 'VuFind\Content\Covers\LibraryThing',
                    'openlibrary' => 'VuFind\Content\Covers\OpenLibrary',
                    'summon' => 'VuFind\Content\Covers\Summon',
                ],
            ],
            'content_reviews' => [
                'factories' => [
                    'amazon' => 'VuFind\Content\Reviews\Factory::getAmazon',
                    'amazoneditorial' => 'VuFind\Content\Reviews\Factory::getAmazonEditorial',
                    'booksite' => 'VuFind\Content\Reviews\Factory::getBooksite',
                    'syndetics' => 'VuFind\Content\Reviews\Factory::getSyndetics',
                    'syndeticsplus' => 'VuFind\Content\Reviews\Factory::getSyndeticsPlus',
                ],
                'invokables' => [
                    'guardian' => 'VuFind\Content\Reviews\Guardian',
                ],
            ],
            'db_table' => [
                'abstract_factories' => ['VuFind\Db\Table\PluginFactory'],
                'factories' => [
                    'resource' => 'VuFind\Db\Table\Factory::getResource',
                    //'user' => 'VuFind\Db\Table\Factory::getUser',
                	'user' => 'AkSearch\Db\Table\Factory::getUser',
                	'loans' => 'AkSearch\Db\Table\Factory::getLoans'
                ],
                'invokables' => [
                    'changetracker' => 'VuFind\Db\Table\ChangeTracker',
                    'comments' => 'VuFind\Db\Table\Comments',
                    'oairesumption' => 'VuFind\Db\Table\OaiResumption',
                    'resourcetags' => 'VuFind\Db\Table\ResourceTags',
                    'search' => 'VuFind\Db\Table\Search',
                    'session' => 'VuFind\Db\Table\Session',
                    'tags' => 'VuFind\Db\Table\Tags',
                    'userlist' => 'VuFind\Db\Table\UserList',
                    'userresource' => 'VuFind\Db\Table\UserResource',
                    'userstats' => 'VuFind\Db\Table\UserStats',
                    'userstatsfields' => 'VuFind\Db\Table\UserStatsFields',
                ],
            ],
            'hierarchy_driver' => [
                'factories' => [
                    'default' => 'VuFind\Hierarchy\Driver\Factory::getHierarchyDefault',
                    'flat' => 'VuFind\Hierarchy\Driver\Factory::getHierarchyFlat',
                ],
            ],
            'hierarchy_treedataformatter' => [
                'invokables' => [
                    'json' => 'VuFind\Hierarchy\TreeDataFormatter\Json',
                    'xml' => 'VuFind\Hierarchy\TreeDataFormatter\Xml',
                ],
            ],
            'hierarchy_treedatasource' => [
                'factories' => [
                    'solr' => 'VuFind\Hierarchy\TreeDataSource\Factory::getSolr',
                ],
                'invokables' => [
                    'xmlfile' => 'VuFind\Hierarchy\TreeDataSource\XMLFile',
                ],
            ],
            'hierarchy_treerenderer' => [
                'factories' => [
                    'jstree' => 'VuFind\Hierarchy\TreeRenderer\Factory::getJSTree'
                ],
            ],
            'ils_driver' => [
                'abstract_factories' => ['VuFind\ILS\Driver\PluginFactory'],
                'factories' => [
                    'aleph' => 'AkSearch\ILS\Driver\Factory::getAleph',
                    'alma'	=> 'AkSearch\ILS\Driver\Factory::getAlma',
                    'daia' => 'VuFind\ILS\Driver\Factory::getDAIA',
                    'demo' => 'VuFind\ILS\Driver\Factory::getDemo',
                    'horizon' => 'VuFind\ILS\Driver\Factory::getHorizon',
                    'horizonxmlapi' => 'VuFind\ILS\Driver\Factory::getHorizonXMLAPI',
                    'multibackend' => 'VuFind\ILS\Driver\Factory::getMultiBackend',
                    'noils' => 'VuFind\ILS\Driver\Factory::getNoILS',
                    'unicorn' => 'VuFind\ILS\Driver\Factory::getUnicorn',
                    'voyager' => 'VuFind\ILS\Driver\Factory::getVoyager',
                    'voyagerrestful' => 'VuFind\ILS\Driver\Factory::getVoyagerRestful',
                ],
                'invokables' => [
                    //'alma' => 'AkSearch\ILS\Driver\Alma',
                    //'aleph' => 'AkSearch\ILS\Driver\Aleph',
                    'amicus' => 'VuFind\ILS\Driver\Amicus',
                    'claviussql' => 'VuFind\ILS\Driver\ClaviusSQL',
                    'evergreen' => 'VuFind\ILS\Driver\Evergreen',
                    'innovative' => 'VuFind\ILS\Driver\Innovative',
                    'koha' => 'VuFind\ILS\Driver\Koha',
                    'lbs4' => 'VuFind\ILS\Driver\LBS4',
                    'newgenlib' => 'VuFind\ILS\Driver\NewGenLib',
                    'polaris' => 'VuFind\ILS\Driver\Polaris',
                    'sample' => 'VuFind\ILS\Driver\Sample',
                    'sierra' => 'VuFind\ILS\Driver\Sierra',
                    'symphony' => 'VuFind\ILS\Driver\Symphony',
                    'virtua' => 'VuFind\ILS\Driver\Virtua',
                    'xcncip2' => 'VuFind\ILS\Driver\XCNCIP2',
                ],
            ],
            'recommend' => [
                'abstract_factories' => ['VuFind\Recommend\PluginFactory'],
                'factories' => [
                    'authorfacets' => 'VuFind\Recommend\Factory::getAuthorFacets',
                    'authorinfo' => 'VuFind\Recommend\Factory::getAuthorInfo',
                    'authorityrecommend' => 'VuFind\Recommend\Factory::getAuthorityRecommend',
                    'catalogresults' => 'VuFind\Recommend\Factory::getCatalogResults',
                    'collectionsidefacets' => 'VuFind\Recommend\Factory::getCollectionSideFacets',
                    'dplaterms' => 'VuFind\Recommend\Factory::getDPLATerms',
                    'europeanaresults' => 'VuFind\Recommend\Factory::getEuropeanaResults',
                    'expandfacets' => 'VuFind\Recommend\Factory::getExpandFacets',
                    'favoritefacets' => 'VuFind\Recommend\Factory::getFavoriteFacets',
                    //'sidefacets' => 'VuFind\Recommend\Factory::getSideFacets',
                	'sidefacets' => 'AkSearch\Recommend\Factory::getSideFacets',
                    'randomrecommend' => 'VuFind\Recommend\Factory::getRandomRecommend',
                    'summonbestbets' => 'VuFind\Recommend\Factory::getSummonBestBets',
                    'summondatabases' => 'VuFind\Recommend\Factory::getSummonDatabases',
                    'summonresults' => 'VuFind\Recommend\Factory::getSummonResults',
                    'summontopics' => 'VuFind\Recommend\Factory::getSummonTopics',
                    'switchquery' => 'VuFind\Recommend\Factory::getSwitchQuery',
                    'topfacets' => 'VuFind\Recommend\Factory::getTopFacets',
                    'visualfacets' => 'VuFind\Recommend\Factory::getVisualFacets',
                    'webresults' => 'VuFind\Recommend\Factory::getWebResults',
                    'worldcatidentities' => 'VuFind\Recommend\Factory::getWorldCatIdentities',
                    'worldcatterms' => 'VuFind\Recommend\Factory::getWorldCatTerms',
                ],
                'invokables' => [
                    'alphabrowselink' => 'VuFind\Recommend\AlphaBrowseLink',
                    'europeanaresultsdeferred' => 'VuFind\Recommend\EuropeanaResultsDeferred',
                    'facetcloud' => 'VuFind\Recommend\FacetCloud',
                    'openlibrarysubjects' => 'VuFind\Recommend\OpenLibrarySubjects',
                    'openlibrarysubjectsdeferred' => 'VuFind\Recommend\OpenLibrarySubjectsDeferred',
                    'pubdatevisajax' => 'VuFind\Recommend\PubDateVisAjax',
                    'resultgooglemapajax' => 'VuFind\Recommend\ResultGoogleMapAjax',
                    'spellingsuggestions' => 'VuFind\Recommend\SpellingSuggestions',
                    'summonbestbetsdeferred' => 'VuFind\Recommend\SummonBestBetsDeferred',
                    'summondatabasesdeferred' => 'VuFind\Recommend\SummonDatabasesDeferred',
                    'summonresultsdeferred' => 'VuFind\Recommend\SummonResultsDeferred',
                    'switchtype' => 'VuFind\Recommend\SwitchType',
                	'akswitchtype' => 'AkSearch\Recommend\AkSwitchType'
                ],
            ],
            'recorddriver' => [
                'abstract_factories' => ['VuFind\RecordDriver\PluginFactory'],
                'factories' => [
                    'eds' => 'VuFind\RecordDriver\Factory::getEDS',
                    'eit' => 'VuFind\RecordDriver\Factory::getEIT',
                    'missing' => 'VuFind\RecordDriver\Factory::getMissing',
                    'pazpar2' => 'VuFind\RecordDriver\Factory::getPazpar2',
                    'primo' => 'VuFind\RecordDriver\Factory::getPrimo',
                    'solrauth' => 'VuFind\RecordDriver\Factory::getSolrAuth',
                    'solrdefault' => 'VuFind\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'VuFind\RecordDriver\Factory::getSolrMarc',
                    'solrmarcremote' => 'VuFind\RecordDriver\Factory::getSolrMarcRemote',
                    'solrreserves' => 'VuFind\RecordDriver\Factory::getSolrReserves',
                    'solrweb' => 'VuFind\RecordDriver\Factory::getSolrWeb',
                    'summon' => 'VuFind\RecordDriver\Factory::getSummon',
                    'worldcat' => 'VuFind\RecordDriver\Factory::getWorldCat',
                	'solrmab' => 'AkSearch\RecordDriver\Factory::getSolrMab',
                	'akfilter' => 'AkSearch\RecordDriver\Factory::getSolrMab',
                ],
                'invokables' => [
                    'libguides' => 'VuFind\RecordDriver\LibGuides',
                ],
            ],
            'recordtab' => [
                'abstract_factories' => ['VuFind\RecordTab\PluginFactory'],
                'factories' => [
                    'collectionhierarchytree' => 'VuFind\RecordTab\Factory::getCollectionHierarchyTree',
                    'collectionlist' => 'VuFind\RecordTab\Factory::getCollectionList',
                    'excerpt' => 'VuFind\RecordTab\Factory::getExcerpt',
                    'hierarchytree' => 'VuFind\RecordTab\Factory::getHierarchyTree',
                    'holdingsils' => 'VuFind\RecordTab\Factory::getHoldingsILS',
                    'holdingsworldcat' => 'VuFind\RecordTab\Factory::getHoldingsWorldCat',
                    'map' => 'VuFind\RecordTab\Factory::getMap',
                    'preview' => 'VuFind\RecordTab\Factory::getPreview',
                    'reviews' => 'VuFind\RecordTab\Factory::getReviews',
                    'similaritemscarousel' => 'VuFind\RecordTab\Factory::getSimilarItemsCarousel',
                    'usercomments' => 'VuFind\RecordTab\Factory::getUserComments',
                ],
                'invokables' => [
                    //'description' => 'VuFind\RecordTab\Description',
                	'description' => 'AkSearch\RecordTab\Description',
                    'staffviewarray' => 'VuFind\RecordTab\StaffViewArray',
                    'staffviewmarc' => 'VuFind\RecordTab\StaffViewMARC',
                    'toc' => 'VuFind\RecordTab\TOC',
                ],
                'initializers' => [
                    'ZfcRbac\Initializer\AuthorizationServiceInitializer'
                ],
            ],
            'related' => [
                'abstract_factories' => ['VuFind\Related\PluginFactory'],
                'factories' => [
                    'editions' => 'VuFind\Related\Factory::getEditions',
                    'similar' => 'VuFind\Related\Factory::getSimilar',
                    'worldcateditions' => 'VuFind\Related\Factory::getWorldCatEditions',
                    'worldcatsimilar' => 'VuFind\Related\Factory::getWorldCatSimilar',
                ],
            ],
            'resolver_driver' => [
                'abstract_factories' => ['VuFind\Resolver\Driver\PluginFactory'],
                'factories' => [
                    '360link' => 'VuFind\Resolver\Driver\Factory::getThreesixtylink',
                    'ezb' => 'VuFind\Resolver\Driver\Factory::getEzb',
                    'sfx' => 'VuFind\Resolver\Driver\Factory::getSfx',
                    'redi' => 'VuFind\Resolver\Driver\Factory::getRedi',
                ],
                'aliases' => [
                    'threesixtylink' => '360link',
                ],
            ],
            'search_backend' => [
                'factories' => [
                	'Akfilter' => 'AkSearch\Search\Factory\AkfilterBackendFactory',
                    'EDS' => 'VuFind\Search\Factory\EdsBackendFactory',
                    'EIT' => 'VuFind\Search\Factory\EITBackendFactory',
                    'LibGuides' => 'VuFind\Search\Factory\LibGuidesBackendFactory',
                    'Pazpar2' => 'VuFind\Search\Factory\Pazpar2BackendFactory',
                    'Primo' => 'VuFind\Search\Factory\PrimoBackendFactory',
                    //'Solr' => 'VuFind\Search\Factory\SolrDefaultBackendFactory',
                    'Solr' => 'AkSearch\Search\Factory\SolrDefaultBackendFactory',
                    'SolrAuth' => 'VuFind\Search\Factory\SolrAuthBackendFactory',
                    'SolrReserves' => 'VuFind\Search\Factory\SolrReservesBackendFactory',
                    'SolrStats' => 'VuFind\Search\Factory\SolrStatsBackendFactory',
                    'SolrWeb' => 'VuFind\Search\Factory\SolrWebBackendFactory',
                    'Summon' => 'VuFind\Search\Factory\SummonBackendFactory',
                    'WorldCat' => 'VuFind\Search\Factory\WorldCatBackendFactory',
                ],
                'aliases' => [
                    // Allow Solr core names to be used as aliases for services:
                    'authority' => 'SolrAuth',
                    'biblio' => 'Solr',
                    'reserves' => 'SolrReserves',
                    'stats' => 'SolrStats',
                    // Legacy:
                    'VuFind' => 'Solr',
                ]
            ],
            'search_options' => [
            	'abstract_factories' => [
            		'VuFind\Search\Options\PluginFactory',
            		'AkSearch\Search\Options\PluginFactory'
            	],
                'factories' => [
                    'eds' => 'VuFind\Search\Options\Factory::getEDS',
                ],
            ],
            'search_params' => [
            	'abstract_factories' => [
            		'VuFind\Search\Params\PluginFactory',
            		'AkSearch\Search\Params\PluginFactory'
            	],
            ],
            'search_results' => [
                'abstract_factories' => [
					'VuFind\Search\Results\PluginFactory',
                	'AkSearch\Search\Results\PluginFactory'
                ],
                'factories' => [
                    'favorites' => 'VuFind\Search\Results\Factory::getFavorites',
                    'solr' => 'VuFind\Search\Results\Factory::getSolr',
                ],
            ],
            'session' => [
                'abstract_factories' => ['VuFind\Session\PluginFactory'],
                'invokables' => [
                    'database' => 'VuFind\Session\Database',
                    'file' => 'VuFind\Session\File',
                    'memcache' => 'VuFind\Session\Memcache',
                ],
                'aliases' => [
                    // for legacy 1.x compatibility
                    'filesession' => 'File',
                    'memcachesession' => 'Memcache',
                    'mysqlsession' => 'Database',
                ],
            ],
            'statistics_driver' => [
                'abstract_factories' => ['VuFind\Statistics\Driver\PluginFactory'],
                'factories' => [
                    'file' => 'VuFind\Statistics\Driver\Factory::getFile',
                    'solr' => 'VuFind\Statistics\Driver\Factory::getSolr',
                ],
                'invokables' => [
                    'db' => 'VuFind\Statistics\Driver\Db',
                ],
                'aliases' => [
                    'database' => 'db',
                ],
            ],
        ],
        // This section behaves just like recorddriver_tabs below, but is used for
        // the collection module instead of the standard record view.
        'recorddriver_collection_tabs' => [
            'VuFind\RecordDriver\AbstractBase' => [
                'tabs' => [
                    'CollectionList' => 'CollectionList',
                    'HierarchyTree' => 'CollectionHierarchyTree',
                ],
                'defaultTab' => null,
            ],
        ],
        // This section controls which tabs are used for which record driver classes.
        // Each sub-array is a map from a tab name (as used in a record URL) to a tab
        // service (found in recordtab_plugin_manager, below).  If a particular record
        // driver is not defined here, it will inherit configuration from a configured
        // parent class.  The defaultTab setting may be used to specify the default
        // active tab; if null, the value from the relevant .ini file will be used.
        'recorddriver_tabs' => [
            'VuFind\RecordDriver\EDS' => [
                'tabs' => [
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\Pazpar2' => [
                'tabs' => [
                    'Details' => 'StaffViewMARC',
                 ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\Primo' => [
                'tabs' => [
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\SolrAuth' => [
                'tabs' => [
                    'Details' => 'StaffViewMARC',
                 ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\SolrDefault' => [
                'tabs' => [
                    'Holdings' => 'HoldingsILS', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Similar' => 'SimilarItemsCarousel',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\SolrMarc' => [
                'tabs' => [
                    'Holdings' => 'HoldingsILS', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Similar' => 'SimilarItemsCarousel',
                    'Details' => 'StaffViewMARC',
                ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\Summon' => [
                'tabs' => [
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\WorldCat' => [
                'tabs' => [
                    'Holdings' => 'HoldingsWorldCat', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Details' => 'StaffViewMARC',
                ],
                'defaultTab' => null,
            ],
            'AkSearch\RecordDriver\SolrMab' => [
                'tabs' => [
                    'MultiVolumeWorks' => 'AkSearch\RecordTab\MultiVolumeWorks', // Tab added by AK Bibliothek Wien
                    //'JournalHolding' => 'AkSearch\RecordTab\JournalHolding', // Tab added by AK Bibliothek Wien
                    //'BindingUnits' => 'AkSearch\RecordTab\BindingUnits', // Tab added by AK Bibliothek Wien
                    'Holdings' => 'AkSearch\RecordTab\HoldingsILS', // Tab changed by AK Bibliothek Wien
                    'Description' => 'Description',
                    'TOC' => 'TOC',
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Excerpt' => 'Excerpt',
                    'HierarchyTree' => 'HierarchyTree',
                    'Map' => 'Map',
                    'Details' => 'AkSearch\RecordTab\StaffViewAll', // Tab changed by AK Bibliothek Wien
                ],
            ]
        ],
    ],
    // Authorization configuration:
    'zfc_rbac' => [
        'identity_provider' => 'VuFind\AuthManager',
        'guest_role' => 'guest',
        'role_provider' => [
            'VuFind\Role\DynamicRoleProvider' => [
                'map_legacy_settings' => true,
            ],
        ],
        'role_provider_manager' => [
            'factories' => [
                'VuFind\Role\DynamicRoleProvider' => 'VuFind\Role\DynamicRoleProviderFactory',
            ],
        ],
        'vufind_permission_provider_manager' => [
            'factories' => [
                'ipRange' => 'VuFind\Role\PermissionProvider\Factory::getIpRange',
                'ipRegEx' => 'VuFind\Role\PermissionProvider\Factory::getIpRegEx',
                'serverParam' => 'VuFind\Role\PermissionProvider\Factory::getServerParam',
                'shibboleth' => 'VuFind\Role\PermissionProvider\Factory::getShibboleth',
                'username' => 'VuFind\Role\PermissionProvider\Factory::getUsername',
            	'usergroup' => 'AkSearch\Role\PermissionProvider\Factory::getUsergroup',
            ],
            'invokables' => [
                'role' => 'VuFind\Role\PermissionProvider\Role',
            ],
        ],
    ],
];

// Define record view routes -- route name => controller
$recordRoutes = [
	'akfilterrecord' => 'Record',
    'record' => 'Record',
    'collection' => 'Collection',
    'edsrecord' => 'EdsRecord',
    'eitrecord' => 'EITRecord',
    'missingrecord' => 'MissingRecord',
    'primorecord' => 'PrimoRecord',
    'solrauthrecord' => 'Authority',
    'summonrecord' => 'SummonRecord',
    'worldcatrecord' => 'WorldcatRecord'
];

// Define dynamic routes -- controller => [route name => action]
$dynamicRoutes = [
    'MyResearch' => ['userList' => 'MyList/[:id]', 'editList' => 'EditList/[:id]'],
    'LibraryCards' => ['editLibraryCard' => 'editCard/[:id]'],
];

// Define static routes -- Controller/Action strings
$staticRoutes = [
	'Akfilter/Home', 'Akfilter/Results', 'Akfilter/Advanced',
    'AkSites/About', 'AkSites/DataPrivacyStatement', 'AkSites/ChangeUserData', 'AkSites/SetPasswordWithOtp', 'AkSites/Captcha', 'AkSites/LoanHistory', 'AkSites/RequestSetPassword', 'AkSites/SetPassword',
    'Alphabrowse/Home', 'Author/Home', 'Author/Search',
    'Authority/Home', 'Authority/Record', 'Authority/Search',
    'Browse/Author', 'Browse/Dewey', 'Browse/Era', 'Browse/Genre', 'Browse/Home',
    'Browse/LCC', 'Browse/Region', 'Browse/Tag', 'Browse/Topic',
    'Cart/doExport', 'Cart/Email', 'Cart/Export', 'Cart/Home', 'Cart/MyResearchBulk',
    'Cart/Save', 'Collections/ByTitle', 'Collections/Home',
    'Combined/Home', 'Combined/Results', 'Combined/SearchBox', 'Confirm/Confirm',
    'Cover/Show', 'Cover/Unavailable',
    'EDS/Advanced', 'EDS/Home', 'EDS/Search',
    'EIT/Advanced', 'EIT/Home', 'EIT/Search',
    'Error/Unavailable', 'Feedback/Email', 'Feedback/Home', 'Help/Home',
    'Install/Done', 'Install/FixBasicConfig', 'Install/FixCache',
    'Install/FixDatabase', 'Install/FixDependencies', 'Install/FixILS',
    'Install/FixSecurity', 'Install/FixSolr', 'Install/Home',
    'Install/PerformSecurityFix', 'Install/ShowSQL',
    'LibGuides/Home', 'LibGuides/Results',
    'LibraryCards/Home', 'LibraryCards/SelectCard',
    'LibraryCards/DeleteCard',
    'MyResearch/Account', 'MyResearch/ChangePassword', 'MyResearch/CheckedOut',
    'MyResearch/Delete', 'MyResearch/DeleteList', 'MyResearch/Edit',
    'MyResearch/Email', 'MyResearch/Favorites', 'MyResearch/Fines',
    'MyResearch/Holds', 'MyResearch/Home',
    'MyResearch/ILLRequests', 'MyResearch/Logout',
    'MyResearch/NewPassword', 'MyResearch/Profile',
    'MyResearch/Recover', 'MyResearch/SaveSearch',
    'MyResearch/StorageRetrievalRequests', 'MyResearch/UserLogin',
    'MyResearch/Verify',
    'Primo/Advanced', 'Primo/Home', 'Primo/Search',
    'QRCode/Show', 'QRCode/Unavailable',
    'OAI/Server', 'Pazpar2/Home', 'Pazpar2/Search', 'Records/Home',
    'Search/Advanced', 'Search/Email', 'Search/History', 'Search/Home',
    'Search/NewItem', 'Search/OpenSearch', 'Search/Reserves', 'Search/Results',
    'Search/Suggest',
    'Summon/Advanced', 'Summon/Home', 'Summon/Search',
    'Tag/Home',
    'Upgrade/Home', 'Upgrade/FixAnonymousTags', 'Upgrade/FixDuplicateTags',
    'Upgrade/FixConfig', 'Upgrade/FixDatabase', 'Upgrade/FixMetadata',
    'Upgrade/GetDBCredentials', 'Upgrade/GetDbEncodingPreference',
    'Upgrade/GetSourceDir', 'Upgrade/GetSourceVersion', 'Upgrade/Reset',
    'Upgrade/ShowSQL',
    'Web/Home', 'Web/Results',
    'Worldcat/Advanced', 'Worldcat/Home', 'Worldcat/Search'
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

// Add the home route last
$config['router']['routes']['home'] = [
    'type' => 'Zend\Mvc\Router\Http\Literal',
    'options' => [
        'route'    => '/',
        'defaults' => [
            'controller' => 'index',
            'action'     => 'Home',
        ]
    ]
];

return $config;
