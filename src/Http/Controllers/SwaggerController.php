<?php

namespace Uccello\Api\Http\Controllers;

use L5Swagger\Generator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;

class SwaggerController extends BaseController
{
    protected $domain;

    /**
     * Dump api-docs.json content endpoint.
     *
     * @param string $jsonFile
     *
     * @return \Response
     */
    public function docs(?Domain $domain)
    {
        if (!$domain) {
            $domain = Domain::first();
        }

        $this->domain = $domain;

        return [
            "swagger" => "2.0",
            "info" => [
                "description" => "This is the list of authorized actions for your current user.",
                "version" => "1.0.0",
                "title" => env('APP_NAME') . ' API Documentation',
            ],
            "host" => str_replace(['https://', 'http://'], ['', ''], URL::to('/')) . '/api',
            "basePath" => "",
            "schemes" => [
                request()->getScheme()
            ],
            "paths" => array_merge(
                    $this->generateLoginPath(),
                    $this->generateUserInfoPath(),
                    $this->generateRefreshPath(),
                    $this->generateLogoutPath(),
                    $this->generateModulesPaths()
                ),
            "securityDefinitions" => [
                "Bearer" => [
                    "name" => "Authorization",
                    "type" => "apiKey",
                    "in" => "header",
                    "description" => "Value: Bearer {JWToken}"
                ]
            ],
            "security" => [
                [
                    "Bearer" => []
                ]
            ],
        ];
    }
    /**
     * Display Swagger API page.
     *
     * @return \Response
     */
    public function api(?Domain $domain)
    {
        if (!$domain) {
            $domain = Domain::first();
        }

        if (config('l5-swagger.generate_always')) {
            Generator::generateDocs();
        }
        if ($proxy = config('l5-swagger.proxy')) {
            if (!is_array($proxy)) {
                $proxy = [$proxy];
            }
            Request::setTrustedProxies($proxy, \Illuminate\Http\Request::HEADER_X_FORWARDED_ALL);
        }
        // Need the / at the end to avoid CORS errors on Homestead systems.
        $response = Response::make(
            view('l5-swagger::index', [
                'secure' => Request::secure(),
                'urlToDocs' => ucroute('api.uccello.doc.json', $domain),
                'operationsSorter' => config('l5-swagger.operations_sort'),
                'configUrl' => config('l5-swagger.additional_config_url'),
                'validatorUrl' => config('l5-swagger.validator_url'),
            ]),
            200
        );
        return $response;
    }

    protected function generateLoginPath()
    {
        return [
            "/auth/login" => [
                "post" => [
                    "summary" => "Login",
                    "description" => "The Login endpoint allows an user to authentificate itself using his credentials.
                    You must use the returned token in the header of _all your other calls_ to the API.
                    **Autorization: Bearer {token}**",
                    "consumes" => [
                        "multipart/form-data"
                    ],
                    "parameters" => [
                        [
                            "name" => "login",
                            "in" => "formData",
                            "description" => "The user name for login",
                            "required" => true,
                            "type" => "string"
                        ],
                        [
                            "name" => "password",
                            "in" => "formData",
                            "description" => "The password for login in clear text",
                            "required" => true,
                            "type" => "string",
                            "format" => "password"
                        ]
                    ],
                    "tags" => [
                        "Auth",
                    ],
                    "responses" => [
                        "200" => [
                            "description" => "Token to use in all your calls to the API"
                        ],

                        "400" => [
                            "description" => "Invalid username/password supplied"
                        ],

                        "default" => [
                            "description" => "Unexpected error"
                        ]
                    ]
                ]
            ]
        ];
    }

    protected function generateUserInfoPath()
    {
        return [
            "/auth/me" => [
                "get" => [
                    "summary" => "User info",
                    "description" => "Get all data about the user connected.",
                    "tags" => [
                        "Auth",
                    ],
                    "responses" => [
                        "200" => [
                            "description" => "Connected user data"
                        ],

                        "default" => [
                            "description" => "Unexpected error"
                        ]
                    ]
                ]
            ]
        ];
    }

    protected function generateRefreshPath()
    {
        return [
            "/auth/refresh" => [
                "get" => [
                    "summary" => "Refresh token",
                    "description" => "Refresh JWToken.",
                    "tags" => [
                        "Auth"
                    ],
                    "responses" => [
                        "200" => [
                            "description" => "Connected user data"
                        ],

                        "default" => [
                            "description" => "Unexpected error"
                        ]
                    ]
                ]
            ]
        ];
    }

    protected function generateLogoutPath()
    {
        return [
            "/auth/logout" => [
                "get" => [
                    "summary" => "Logout",
                    "description" => "Logout User",
                    "tags" => [
                        "Auth",
                    ],
                    "responses" => [
                        "200" => [
                            "description" => "User logged out"
                        ],

                        "default" => [
                            "description" => "Unexpected error"
                        ]
                    ]
                ]
            ]
        ];
    }

    protected function generateModulesPaths(): array
    {
        $paths = [];

        $modules = $this->domain->modules()->whereNotNull('model_class')->get();

        $domain = $this->domain;
        $domainSlug = $this->domain->slug;
        $user = auth()->user();

        foreach ($modules as $module) {
            $moduleName = $module->name;

            // $describePath = method_exists($module, 'generateDescribePath') ? $module->generateDescribePath($this->domain, $module) : $this->generateDescribePath($module);
            // $countPath = method_exists($module, 'generateCountPath') ? $module->generateCountPath($this->domain, $module) : $this->generateCountPath($module);

            if ($user->canRetrieveByApi($domain, $module)) {
                $listPath = method_exists($module, 'generateListPath') ? $module->generateListPath($this->domain, $module) : $this->generateListPath($module);
                $retrievePath = method_exists($module, 'generateRetrievePath') ? $module->generateRetrievePath($this->domain, $module) : $this->generateRetrievePath($module);
            }

            if ($user->canCreateByApi($domain, $module)) {
                $createPath = method_exists($module, 'generateCreatePath') ? $module->generateCreatePath($this->domain, $module) : $this->generateCreatePath($module);
            }

            if ($user->canUpdateByApi($domain, $module)) {
                $updatePath = method_exists($module, 'generateUpdatePath') ? $module->generateUpdatePath($this->domain, $module) : $this->generateUpdatePath($module);
            }

            if ($user->canDeleteByApi($domain, $module)) {
                $deletePath = method_exists($module, 'generateDeletePath') ? $module->generateDeletePath($this->domain, $module) : $this->generateDeletePath($module);
            }

            // List, Create
            $paths["/$domainSlug/$moduleName"] = array_merge(
                $listPath,
                $createPath
            );

            if(empty($paths["/$domainSlug/$moduleName"])){
                unset($paths["/$domainSlug/$moduleName"]);
            }

            // Retrieve, Update
            $paths["/$domainSlug/$moduleName/{id}"] = array_merge(
                $retrievePath,
                $updatePath,
                $deletePath
            );

            if(empty($paths["/$domainSlug/$moduleName/{id}"])){
                unset($paths["/$domainSlug/$moduleName/{id}"]);
            }
        }

        return $paths;
    }

    protected function generateListPath(Module $module): array
    {
        $moduleName = $module->name;
        $moduleNameTranslated = uctrans($moduleName, $module);

        if (!class_exists($module->model_class)) {
            return [];
        }

        $path = [
            "get" => [
                "summary" => "Get list of $moduleNameTranslated",
                "description" => "",
                "parameters" => [],
                "tags" => [
                    uctrans($moduleName, $module)
                ],
                "responses" => [
                    "200" => [
                        "description" => "An array of $moduleNameTranslated"
                    ],

                    "default" => [
                        "description" => "Unexpected error"
                    ]
                ]
            ]
        ];

        // Select
        $path["get"]["parameters"][] = [
            "name" => "select",
            "in" => "query",
            "description" => "List of columns to retrieve. Use ';' as separator.",
            "required" => false,
            "type" => "string"
        ];

        // Page
        $path["get"]["parameters"][] = [
            "name" => "page",
            "in" => "query",
            "description" => "Pagination page index. Default: 0.",
            "required" => false,
            "type" => "integer"
        ];

        // Length
        $path["get"]["parameters"][] = [
            "name" => "length",
            "in" => "query",
            "description" => "Pagination length. ".
                "Default: ".config('uccello.api.items_per_page', 100).". ".
                "Max: ".config('uccello.api.max_items_per_page', 100).".",
            "required" => false,
            "type" => "integer"
        ];

        // Order
        $path["get"]["parameters"][] = [
            "name" => "order",
            "in" => "query",
            "description" => "Order by - _field1,direction1;...;fieldN,directionN_. Default: ".((new $module->model_class)->getKeyName()).",asc",
            "required" => false,
            "type" => "string"
        ];

        // With
        $path["get"]["parameters"][] = [
            "name" => "with",
            "in" => "query",
            "description" => "List of related to retrieve automaticaly. Use ';' as separator.",
            "required" => false,
            "type" => "integer"
        ];

        // If "searchable" property exists in the entity class, add "q" parameter
        if(property_exists($module->model_class, "searchableColumns"))
        {
            $searchable = (new $module->model_class)->searchableColumns;

            $path["get"]["parameters"][] = [
                "name" => "q",
                "in" => "query",
                "description" => "Allows you to search if a string is contained in the following fields:\n".
                                "- ".implode("\n- ", $searchable),
                "required" => false,
                "type" => "string"
            ];
        }

        // $path["get"]["parameters"][] = [
        //     "name" => "conditions",
        //     "in" => "query",
        //     "description" => "Complex search conditions.\n".
        //                     "Allows you to search all entities matching with your conditions.\n\n".
        //                     "Example:\n".
        //                     "\n    ".'[{"field":"name","operator":"c","value":"doe"}, {"field":"age","operator":"e","value":25}]'."\n\n".
        //                     "**Operators available**\n\n".
        //                     "_String:_\n".
        //                     "- **e** - Equals\n".
        //                     "- **c** - Contains\n".
        //                     "- **bw** - Begins with\n".
        //                     "- **ew** - Ends with\n".
        //                     "- **ne** - Does not equal\n".
        //                     "- **nc** - Does not contain\n".
        //                     "- **nbw** - Does not begin with\n".
        //                     "- **new** - Does not end with\n\n".
        //                     "_Number:_\n".
        //                     "- **e** - Equals\n".
        //                     "- **lt** - Lower than\n".
        //                     "- **lte** - Lower than or equal\n".
        //                     "- **gt** - Greater than\n".
        //                     "- **gte** - Greater than or equal\n\n".
        //                     "_Date / Datetime:_\n".
        //                     "- **de** - Equals\n".
        //                     "- **dlt** - Lower than\n".
        //                     "- **dlte** - Lower than or equal\n".
        //                     "- **dgt** - Greater than\n".
        //                     "- **dgte** - Greater than or equal\n".
        //                     "- **dne** - Does not equal\n\n".
        //                     "_Geographical point:_\n".
        //                     "- **rlte** - Radius lower than or equal\n".
        //                     "- **rgte** - Radius greater than or equal\n\n".
        //                     "_Value:_ longitude, latitude, radius (meters)\n".
        //                     "\n    ".'Example: [{"field":"addr_coordinates","operator":"rlte","value":"43.6543442,3.9762454,200"}]'."\n\n".
        //                     "Search all data with **addr_coordinates** located in a radius lower than or equal to **200 meters** around the point **_Lat:_ 43.6543442, _Lng:_ 3.9762454**.",
        //     "required" => false,
        //     "type" => "json"
        // ];

        return $path;
    }

    protected function generateRetrievePath(Module $module): array
    {
        $moduleName = $module->name;
        $moduleNameTranslated = uctrans($moduleName, $module);

        return [
            "get" => [
                "summary" => "Retrieve a record",
                "description" => "",
                "parameters" => [
                    [
                        "name" => "id",
                        "in" => "path",
                        "description" => "Record id",
                        "required" => true,
                        "type" => "integer",
                        "format" => "int64"
                    ]
                ],
                "tags" => [
                    $moduleNameTranslated
                ],
                "responses" => [
                    "200" => [
                        "description" => "A record",
                    ],

                    "default" => [
                        "description" => "Unexpected error"
                    ]
                ]
            ]
        ];
    }

    protected function generateCreatePath(Module $module): array
    {
        $moduleName = $module->name;
        $moduleNameTranslated = uctrans($moduleName, $module);

        $path = [
            "post" => [
                "summary" => "Create a new record",
                "description" => "NB: Empty fields will be ignored.",
                "consumes" => [
                    "multipart/form-data",
                ],
                "parameters" => [],
                "tags" => [
                    $moduleNameTranslated
                ],
                "responses" => [
                    "200" => [
                        "description" => "Record created"
                    ],

                    "default" => [
                        "description" => "Unexpected error"
                    ]
                ]
            ]
        ];

        $path["post"]["parameters"] = array_merge(
            $path["post"]["parameters"],
            $this->generateModuleFieldsParameters($module, 'create')
        );

        return $path;
    }

    protected function generateUpdatePath(Module $module): array
    {
        $moduleName = $module->name;
        $moduleNameTranslated = uctrans($moduleName, $module);

        $path = [
            "post" => [
                "summary" => "Update a record",
                "description" => "NB: Empty fields will be ignored. Use **NULL** to unset a value.",
                "consumes" => [
                    "multipart/form-data"
                ],
                "parameters" => [
                    [
                        "name" => "id",
                        "in" => "path",
                        "description" => "Record id",
                        "required" => true,
                        "type" => "integer",
                        "format" => "int64"
                    ]
                ],
                "tags" => [
                    $moduleNameTranslated
                ],
                "responses" => [
                    "200" => [
                        "description" => "Record updated"
                    ],

                    "default" => [
                        "description" => "Unexpected error"
                    ]
                ]
            ]
        ];

        $path["post"]["parameters"] = array_merge(
            $path["post"]["parameters"],
            $this->generateModuleFieldsParameters($module, 'update', false)
        );

        return $path;
    }

    protected function generateDeletePath(Module $module): array
    {
        $moduleName = $module->name;
        $moduleNameTranslated = uctrans($moduleName, $module);

        return [
            "delete" => [
                "summary" => "Delete a record",
                "description" => "",
                "parameters" => [
                    [
                        "name" => "id",
                        "in" => "path",
                        "description" => "Record id",
                        "required" => true,
                        "type" => "integer",
                        "format" => "int64"
                    ]
                ],
                "tags" => [
                    $moduleNameTranslated
                ],
                "responses" => [
                    "200" => [
                        "description" => "Success or not",
                    ],

                    "default" => [
                        "description" => "Unexpected error"
                    ]
                ]
            ]
        ];
    }

    protected function generateModuleFieldsParameters(Module $module, $type, $checkRequired=true)
    {
        $parameters = [];

        foreach ($module->fields()->get() as $field) {
            // We want to display hidden fields, because we can set a value with the API.
            if (($type === 'create' && !$field->isCreateable() && !$field->isHidden()) || ($type === 'update' && !$field->isEditable() && !$field->isHidden())) {
                continue;
            }

            $customDescription = ''; //$this->getFieldCustomDescription($field); //TODO: Add custom description (ex: Date format)

            $parameters[] = [
                "name" => $field->name,
                "in" => "formData",
                "description" => uctrans($field->label, $module) . $customDescription,
                "required" => $checkRequired ? $field->required : false,
                "type" => '', //$this->getParameterType($field) //TODO: Add type
            ];
        }

        return $parameters;
    }
}