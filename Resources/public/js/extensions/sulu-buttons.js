(function() {

    'use strict';

    define([], function() {

        return {

            getButtons: function() {
                return [
                    {
                        name: 'productWorkflow',
                        template: {
                            id: 'workflow',
                            icon: 'husky-deactivated',
                            disabled: true,
                            dropdownItems: [
                                {
                                    id: 'active',
                                    title: 'product.workfow.set.active',
                                    callback: function() {
                                        app.sandbox.emit('sulu.toolbar.productWorkflow.active');
                                    }
                                },
                                {
                                    id: 'inactive',
                                    title: 'product.workfow.set.inactive',
                                    callback: function() {
                                        app.sandbox.emit('sulu.toolbar.productWorkflow.inactive');
                                    }
                                }
                            ]

                        }
                    }
                ];
            }
        };
    });
})();
