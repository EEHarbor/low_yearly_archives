window.doc_page = {
    addon: 'Yearly Archives',
    title: 'Tags',
    sections: [
        {
            title: '',
            type: 'tagtoc',
            desc: 'Yearly Archives has the following front-end tags: ',
        },
        {
            title: '',
            type: 'tags',
            desc: ''
        },
    ],
    tags: [
        {
            tag: '{exp:low_yearly_archives}',
            shortname: 'exp_',
            summary: "",
            desc: "",
            sections: [
                {
                    type: 'params',
                    title: 'Tag Parameters',
                    desc: '',
                    items: [
                        {
                            item: 'author_id',
                            desc: 'Limit the archives to the specified member ID or IDs.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives author_id="1"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'category',
                            desc: 'Limit the archives to the specified category ID or IDs.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives category="1"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'channel',
                            desc: 'The name of the channel you want to limit the query to. Use weblog in EE1.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives channel="blog"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'end_month',
                            desc: '	Number of the month the archives should end with. Defaults to the month of the newest entry.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives end_month="5"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'end_year',
                            desc: '	The year the archives should end with. Defaults to the year of the newest entry.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives end_year="2019"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'monthsort',
                            desc: '	The sort order of the months can be ascending (asc) or descending (desc)',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives monthsort="desc"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'show_expired',
                            desc: '	You can determine whether you wish for entries that have “expired” to be included.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives show_expired="yes"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'show_future_entries',
                            desc: 'You can determine whether you wish for entries dated in the “future” to be included.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives show_future_entries="y"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'sort',
                            desc: '	The sort order of the years can be ascending (asc) or descending (desc).',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives sort="asc"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'start_month',
                            desc: '	Number of the month the archives should start with. Defaults to the month of the oldest entry.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives start_month="11"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'start_year',
                            desc: '	The year the archives should start with. Defaults to the year of the oldest entry.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives start_year="2009"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'status',
                            desc: '	You may restrict to entries with a particular status.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `{exp:low_yearly_archives status="open"}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        
                        
                      
                    ]
                },{
                    type: 'vars',
                    title: 'Variables',
                    desc: '',
                    items: [
                        {
                            item: 'year',
                            desc: 'The 4-digit year.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'year_short',
                            desc: 'The 2-digit year.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'year_count',
                            desc: '	Count of the number of years in the archives.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'total_years',
                            desc: 'Total amount of years in the archives.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'leap_year',
                            desc: 'Set to TRUE when given year is a leap year.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'entries_in_year',
                            desc: '	Amount of entries posted in given year.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: '{months}{/months}',
                            desc: 'Variable pair to loop through the months of a year. This variable pair allows the parameter backspace.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'month',
                            desc: '	Full name of month. Only available inside the {months} variable pair.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'month_num',
                            desc: '	The number of month, with leading zero. Only available inside the {months} variable pair.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'month_num_short',
                            desc: '	The number of month, without leading zero. Only available inside the {months} variable pair.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'month_count',
                            desc: 'Count of the number of months in a year. Only available inside the {months} variable pair.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'total_months',
                            desc: 'Total amount of months in a year. Only available inside the {months} variable pair.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'num_entries',
                            desc: '	Amount of entries posted in month. Only available inside the {months} variable pair.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'num_entries_percentage',
                            desc: 'Percentage of entries posted in month. Only available inside the {months} variable pair.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'num_entries_percentage_rounded',
                            desc: 'Rounded percentage of entries posted in month. Only available inside the {months} variable pair.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: ``,
                                    outputs: ``
                                 }
                             ]
                        },
                        
                      
                    ]
                }
            ]
        }
    ]
};