import React from 'react';

import {CambridgeToolbar} from './cambridge_toolbar.js';

export function OfficialEditor(props) {
    return React.createElement('div', {className: 'dgn-cambridge-editor-ui'},
        React.createElement(CambridgeToolbar, props));
}
