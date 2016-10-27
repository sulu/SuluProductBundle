/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['mvc/relationalmodel'], function (RelationalModel) {

    'use strict';

    return new RelationalModel({
        urlRoot: '/admin/api/currencies',

        defaults: function () {
            return {
                id: '',
                name: '',
                number: '',
                code: ''
            };
        }
    });
});
