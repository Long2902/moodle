import {nodeResolve} from '@rollup/plugin-node-resolve';

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
        nodeResolve({
            browser: true,
        }),
    ],

    treeshake: {
        moduleSideEffects: false,
    },
};
