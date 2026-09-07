import {commands} from './commands.js';

export const keyboardBindings = {
    'Mod-z':
        commands.undo,

    'Shift-Mod-z':
        commands.redo,

    'Mod-y':
        commands.redo,

    'Mod-b':
        commands.toggleBold,

    'Mod-i':
        commands.toggleItalic,

    'Mod-u':
        commands.toggleUnderline,
};
