import {getPluginOptionName} from 'editor_tiny/options';
import {pluginName} from './common';

const enabledName = getPluginOptionName(pluginName, 'enabled');
const contextIdName = getPluginOptionName(pluginName, 'contextid');
const courseIdName = getPluginOptionName(pluginName, 'courseid');
const canUploadName = getPluginOptionName(pluginName, 'canupload');

export const register = (editor) => {
    editor.options.register(enabledName, {processor: 'boolean', default: false});
    editor.options.register(contextIdName, {processor: 'number', default: 0});
    editor.options.register(courseIdName, {processor: 'number', default: 0});
    editor.options.register(canUploadName, {processor: 'boolean', default: false});
};

export const getConfig = (editor) => ({
    enabled: editor.options.get(enabledName),
    contextid: editor.options.get(contextIdName),
    courseid: editor.options.get(courseIdName),
    canupload: editor.options.get(canUploadName),
});
