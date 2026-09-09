import commonjs from '@rollup/plugin-commonjs';
import {nodeResolve} from '@rollup/plugin-node-resolve';
import replace from '@rollup/plugin-replace';

export default {
    input: 'src/index.js',

    output: {
        file: '../amd/build/native_editor.min.js',
        format: 'amd',
        sourcemap: false,
        generatedCode: 'es2015',
        compact: true,
    },

    plugins: [
        replace({
            preventAssignment: true,
            'process.env.NODE_ENV': JSON.stringify('production'),
        }),
        nodeResolve({
            browser: true,
        }),
        commonjs({
            include: /node_modules/,
        }),
    ],

    treeshake: {
        moduleSideEffects: false,
    },
};
