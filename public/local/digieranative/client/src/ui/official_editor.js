import React from 'react';

import {OfficialToolbar} from './toolbar.js';

export function OfficialEditor(props) {
    return React.createElement('div', {className: 'dgn-official-editor-ui'},
        React.createElement(OfficialToolbar, props));
}
