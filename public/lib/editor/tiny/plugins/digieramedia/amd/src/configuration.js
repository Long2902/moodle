import {component as buttonName} from './common';
import {addMenubarItem, addToolbarButton} from 'editor_tiny/utils';

export const configure = (instanceConfig) => ({
    toolbar: addToolbarButton(instanceConfig.toolbar, 'content', buttonName),
    menu: addMenubarItem(instanceConfig.menu, 'insert', buttonName),
});
