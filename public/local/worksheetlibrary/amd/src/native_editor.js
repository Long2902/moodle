define(['core/ajax', 'local_digieranative/native_editor'], function(Ajax, NativeEditor) {
    const labels={saving:'Đang lưu…',saved:'Đã lưu',conflict:'Xung đột phiên bản',error:'Lỗi khi lưu'};
    const init=(config)=>{
        const element=document.getElementById(config.elementid); const status=document.getElementById(config.statusid); if(!element||!status)return;
        const source=JSON.parse(config.nativejson);
        const setStatus=(state)=>{status.dataset.state=state;status.textContent=labels[state]||state;};
        const saveRemote=({nativejson,revision})=>Ajax.call([{methodname:'local_worksheetlibrary_save_native_draft',args:{versionid:Number(config.versionid),expectedrevision:Number(revision),nativejson}}])[0];
        const controller=NativeEditor.createAutosaveController({initialRevision:Number(config.revision)||0,save:saveRemote,onStatus:setStatus});
        const canonicalJson=(canonical)=>JSON.stringify(canonical);
        const publishCanonical=(canonical)=>{
            element._wslibNativeJson=canonical;
            const EventClass=element.ownerDocument.defaultView.CustomEvent;
            element.dispatchEvent(new EventClass('wslib:native-document',{bubbles:true,detail:{nativejson:canonical}}));
        };

        publishCanonical(source);

        NativeEditor.mount({
            element,documentJson:source,readonly:false,collaboration:false,
            save:(canonical)=>controller.saveNow({nativejson:canonicalJson(canonical)}),
            onUpdate:(canonical)=>{
                publishCanonical(canonical);
                if(!controller.blocked())controller.schedule({nativejson:canonicalJson(canonical)});
            },
        });
        setStatus('saved');
    };
    return {init};
});
