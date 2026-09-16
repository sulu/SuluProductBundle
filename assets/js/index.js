// @flow
import {listToolbarActionRegistry} from 'sulu-admin-bundle/views';
import PublishingToolbarAction from './views/List/toolbarActions/PublishingToolbarAction';

listToolbarActionRegistry.add('sulu_product.publishing', PublishingToolbarAction);
