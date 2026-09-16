// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {Dialog} from 'sulu-admin-bundle/components';
import {ResourceRequester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import {AbstractListToolbarAction} from 'sulu-admin-bundle/views';

/**
 * Publishes or unpublishes the selected items in the list's locale, one request per item.
 */
export default class PublishingToolbarAction extends AbstractListToolbarAction {
    @observable loading: boolean = false;
    @observable showUnpublishDialog: boolean = false;

    // an untranslated (ghost) item has no content to publish in this locale
    get publishableItems(): Array<Object> {
        return this.listStore.selections.filter((item) => !item.ghostLocale);
    }

    get unpublishableItems(): Array<Object> {
        return this.listStore.selections.filter((item) => !item.ghostLocale && !!item.published);
    }

    getNode() {
        return (
            <Dialog
                cancelText={translate('sulu_admin.cancel')}
                confirmLoading={this.loading}
                confirmText={translate('sulu_admin.ok')}
                key="sulu_product.publishing"
                onCancel={this.handleUnpublishCancel}
                onConfirm={this.handleUnpublishConfirm}
                open={this.showUnpublishDialog}
                title={translate('sulu_product.unpublish_selection_warning_title')}
            >
                {translate('sulu_product.unpublish_selection_warning_text')}
            </Dialog>
        );
    }

    getToolbarItemConfig() {
        return {
            disabled: this.publishableItems.length === 0,
            icon: 'su-publish',
            label: translate('sulu_product.publishing'),
            loading: this.loading,
            options: [
                {
                    disabled: this.publishableItems.length === 0,
                    label: translate('sulu_admin.publish'),
                    onClick: this.handlePublishClick,
                },
                {
                    disabled: this.unpublishableItems.length === 0,
                    label: translate('sulu_page.unpublish'),
                    onClick: action(() => {
                        this.showUnpublishDialog = true;
                    }),
                },
            ],
            type: 'dropdown',
        };
    }

    handlePublishClick = () => {
        this.applyTransition('publish', this.publishableItems);
    };

    @action handleUnpublishConfirm = () => {
        this.applyTransition('unpublish', this.unpublishableItems);
    };

    @action handleUnpublishCancel = () => {
        this.showUnpublishDialog = false;
    };

    @action applyTransition(transition: string, items: Array<Object>) {
        const {listStore} = this;
        this.loading = true;

        Promise.all(items.map((item) => ResourceRequester.post(
            listStore.resourceKey,
            undefined,
            {...listStore.queryOptions, action: transition, id: item.id}
        ).then(() => undefined, getErrorMessage))).then(action((errors) => {
            errors.filter(Boolean).forEach((error) => this.list.errors.push(error));

            this.loading = false;
            this.showUnpublishDialog = false;
            listStore.clearSelection();
            listStore.reload();
        }));
    }
}

// a failed request rejects with the fetch response
function getErrorMessage(response: Object): Promise<string> {
    const fallback = translate('sulu_admin.unexpected_error');

    if (typeof response?.json !== 'function') {
        return Promise.resolve(fallback);
    }

    return response.json()
        .then((data) => data?.detail || data?.title || fallback)
        .catch(() => fallback);
}
