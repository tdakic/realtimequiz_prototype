/**
 * Template management code.
 *
 * @module realtimequizaccess_seb/managetemplates
 * @copyright  2020 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 */
define(
    ['jquery', 'core/ajax', 'core/str', 'core/notification'],
    function($, ajax, str, notification) {
        var manager = {
            /**
             * Confirm removal of the specified template.
             *
             * @method removeTemplate
             * @param {EventFacade} e The EventFacade
             */
            removeTemplate: function(e) {
                e.preventDefault();
                var targetUrl = $(e.currentTarget).attr('href');
                str.get_strings([
                    {
                        key:        'confirmtemplateremovaltitle',
                        component:  'realtimequizaccess_seb'
                    },
                    {
                        key:        'confirmtemplateremovalquestion',
                        component:  'realtimequizaccess_seb'
                    },
                    {
                        key:        'yes',
                        component:  'moodle'
                    },
                    {
                        key:        'no',
                        component:  'moodle'
                    }
                ])
                .then(function(s) {
                    notification.confirm(s[0], s[1], s[2], s[3], function() {
                        window.location = targetUrl;
                    });

                    return;
                })
                .catch();
            },

            /**
             * Setup the template management UI.
             *
             * @method setup
             */
            setup: function() {
                $('body').delegate('[data-action="delete"]', 'click', manager.removeTemplate);
            }
        };

        return /** @alias module:realtimequizaccess_seb/managetemplates */ {
            /**
             * Setup the template management UI.
             *
             * @method setup
             */
            setup: manager.setup
        };
    });
