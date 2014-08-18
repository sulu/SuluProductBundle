/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([], function () {
    'use strict';

    return {
        name: 'Sulu Product Variants List',

        view: true,

        templates: ['/admin/product/template/product/variants'],

        initialize: function () {
            this.render();
        },

        render: function () {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/variants'));
        }
    };
});
