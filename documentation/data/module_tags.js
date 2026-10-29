window.doc_page = {
    "addon": "Low Yearly Archives",
    "title": "Tag Reference",
    "sections": [
        {
            "title": "",
            "type": "tagtoc",
            "desc": "Yearly Archives has the following front-end tags: "
        },
        {
            "title": "",
            "type": "tags",
            "desc": ""
        }
    ],
    "tags": [
        {
            "tag": "{exp:low_yearly_archives}",
            "shortname": "exp_",
            "summary": "",
            "desc": "Monthly archive listings grouped by year.",
            "sections": [
                {
                    "type": "params",
                    "title": "Tag Parameters",
                    "desc": "",
                    "items": [
                        {
                            "item": "author_id",
                            "desc": "Limit the archives to the specified member ID or IDs.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives author_id=\"1\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "category",
                            "desc": "Limit the archive counts to the specified category ID or IDs. Counts come from matching category rows; an entry matching several selected categories can be counted more than once.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives category=\"1\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "channel",
                            "desc": "Limit the archive counts to the named channel. The current package uses `channel`; the EE1 `weblog` parameter belongs to the historical version.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives channel=\"blog\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "end_month",
                            "desc": "Last month to display in the ending year (1–12). Defaults to the month of the newest matching entry. Intermediate years include all twelve months.",
                            "type": "integer",
                            "accepts": "1–12",
                            "default": "month of newest matching entry",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives end_month=\"5\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "end_year",
                            "desc": "Last year to display. Defaults to the year of the newest matching entry.",
                            "type": "",
                            "accepts": "",
                            "default": "year of newest matching entry",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives end_year=\"2019\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "monthsort",
                            "desc": "The sort order of the months can be ascending (asc) or descending (desc)",
                            "type": "string",
                            "accepts": "asc or desc",
                            "default": "asc",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives monthsort=\"desc\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "show_expired",
                            "desc": "You can determine whether you wish for entries that have “expired” to be included.",
                            "type": "yes/no",
                            "accepts": "yes or no",
                            "default": "no",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives show_expired=\"yes\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "show_future_entries",
                            "desc": "You can determine whether you wish for entries dated in the “future” to be included.",
                            "type": "yes/no",
                            "accepts": "yes or no",
                            "default": "no",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives show_future_entries=\"yes\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "sort",
                            "desc": "The sort order of the years can be ascending (asc) or descending (desc).",
                            "type": "string",
                            "accepts": "asc or desc",
                            "default": "desc",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives sort=\"asc\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "start_month",
                            "desc": "First month to display in the starting year (1–12). Defaults to the month of the oldest matching entry. Intermediate years include all twelve months.",
                            "type": "integer",
                            "accepts": "1–12",
                            "default": "month of oldest matching entry",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives start_month=\"11\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "start_year",
                            "desc": "First year to display. Defaults to the year of the oldest matching entry.",
                            "type": "",
                            "accepts": "",
                            "default": "year of oldest matching entry",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives start_year=\"2009\"}",
                                    "outputs": ""
                                }
                            ]
                        },
                        {
                            "item": "status",
                            "desc": "Limit entries to a particular status. The default is `open`. The existing `not closed` example also includes other non-closed statuses; use `open` when that is the intended public archive.",
                            "type": "",
                            "accepts": "",
                            "default": "open",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_yearly_archives status=\"open\"}",
                                    "outputs": ""
                                }
                            ]
                        }
                    ]
                },
                {
                    "type": "vars",
                    "title": "Variables",
                    "desc": "",
                    "items": [
                        {
                            "item": "year",
                            "desc": "The 4-digit year.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{year}"
                                }
                            ]
                        },
                        {
                            "item": "year_short",
                            "desc": "The 2-digit year.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{year_short}"
                                }
                            ]
                        },
                        {
                            "item": "year_count",
                            "desc": "The current year’s position in the displayed archive, starting at 1.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{year_count}"
                                }
                            ]
                        },
                        {
                            "item": "total_years",
                            "desc": "The number of displayed years, including empty years between the selected start and end years.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{total_years}"
                                }
                            ]
                        },
                        {
                            "item": "leap_year",
                            "desc": "A conditional value of 1 when the displayed year is a leap year, otherwise 0.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{if leap_year}Leap year{/if}"
                                }
                            ]
                        },
                        {
                            "item": "entries_in_year",
                            "desc": "Number of matching entries in the year. This is the full year’s count, including matching months outside a displayed start/end-month range.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{entries_in_year}"
                                }
                            ]
                        },
                        {
                            "item": "{months}{/months}",
                            "desc": "Variable pair to loop through the months of a year. This variable pair allows the parameter backspace.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{months backspace=\"2\"}{month}: {num_entries}, {/months}"
                                }
                            ]
                        },
                        {
                            "item": "month",
                            "desc": "Full name of month. Only available inside the {months} variable pair.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{month}"
                                }
                            ]
                        },
                        {
                            "item": "month_num",
                            "desc": "The number of month, with leading zero. Only available inside the {months} variable pair.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{month_num}"
                                }
                            ]
                        },
                        {
                            "item": "month_num_short",
                            "desc": "The number of month, without leading zero. Only available inside the {months} variable pair.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{month_num_short}"
                                }
                            ]
                        },
                        {
                            "item": "month_count",
                            "desc": "The current month’s position in the displayed year, starting at 1. Available inside `months`.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{month_count}"
                                }
                            ]
                        },
                        {
                            "item": "total_months",
                            "desc": "The number of displayed months for the year, including months with no matching entries. Available inside `months`.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{total_months}"
                                }
                            ]
                        },
                        {
                            "item": "num_entries",
                            "desc": "Amount of entries posted in month. Only available inside the {months} variable pair.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{num_entries}"
                                }
                            ]
                        },
                        {
                            "item": "num_entries_percentage",
                            "desc": "The month’s matching entry count divided by the full year’s matching entry count, multiplied by 100. Available inside `months`; months with no entries output 0.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{num_entries_percentage}"
                                }
                            ]
                        },
                        {
                            "item": "num_entries_percentage_rounded",
                            "desc": "The month’s percentage of the full year’s matching entries, rounded to the nearest integer. Available inside `months`.",
                            "type": "",
                            "accepts": "",
                            "default": "",
                            "required": false,
                            "added": "",
                            "examples": [
                                {
                                    "tag_example": "{num_entries_percentage_rounded}"
                                }
                            ]
                        },
                        {
                            "item": "month_short",
                            "desc": "The abbreviated month name. Available inside `months`.",
                            "examples": [
                                {
                                    "tag_example": "{months}\n    {month_short}\n{/months}"
                                }
                            ]
                        }
                    ]
                }
            ],
            "anchor": "tag-exp_"
        }
    ],
    "terms": [],
    "desc": "The `{exp:low_yearly_archives}` tag pair lists monthly archive counts grouped by year. See the <a href=\"/low-yearly-archives/documentation/examples\">complete example</a> for a year/month archive list. Parameter examples below show opening-tag syntax."
};
