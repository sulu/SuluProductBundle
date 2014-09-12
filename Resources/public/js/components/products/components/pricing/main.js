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

    var render = function () {
        this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/pricing'));
    };

    return {
        name: 'Sulu Product Pricing View',

        view: true,

        templates: ['/admin/product/template/product/pricing'],

        initialize: function () {
            render.call(this);
        }
    };
});
