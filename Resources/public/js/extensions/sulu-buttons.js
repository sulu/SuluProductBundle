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
                            title: 'product.workflow.activate.title',
                            icon: 'husky-deactivated',
                            disabled: true,
                            dropdownItems: [
                                {
                                    id: 'active',
                                    title: 'product.workflow.set.active',
                                    callback: function() {
                                        app.sandbox.emit('sulu.toolbar.productWorkflow.active');
                                    }
                                },
                                {
                                    id: 'inactive',
                                    title: 'product.workflow.set.inactive',
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
