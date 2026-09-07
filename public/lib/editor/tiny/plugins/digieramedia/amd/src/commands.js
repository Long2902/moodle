import {getString} from 'core/str';
import {buttonName, component} from './common';
import {getConfig} from './options';
import {open} from './modal';

export const getSetup = async() => {
    const title = await getString('buttontitle', component);
    return (editor) => {
        if (!getConfig(editor).enabled) {
            return;
        }
        editor.ui.registry.addButton(buttonName, {
            text: title,
            tooltip: title,
            onAction: () => open(editor),
        });
        editor.ui.registry.addMenuItem(buttonName, {
            text: title,
            onAction: () => open(editor),
        });
    };
};
