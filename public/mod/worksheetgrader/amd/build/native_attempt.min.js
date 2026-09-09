define(['core/ajax','local_digieranative/native_editor'],function(Ajax,NativeEditor){
 const labels={saving:'Đang lưu…',saved:'Đã lưu',conflict:'Xung đột phiên bản',error:'Lỗi khi lưu',readonly:'Chỉ xem'};
 const init=(config)=>{const element=document.getElementById(config.elementid),status=document.getElementById(config.statusid),form=config.formid?document.getElementById(config.formid):null;if(!element||!status)return;const source=JSON.parse(config.nativejson),canedit=Boolean(config.canedit);let editor=null,submitting=false;const setStatus=s=>{status.dataset.state=s;status.textContent=labels[s]||s;};
 if(!canedit){editor=NativeEditor.mount({element,documentJson:source,readonly:true,collaboration:false,save:null});setStatus('readonly');return;}
 const saveRemote=({nativejson,revision})=>Ajax.call([{methodname:'mod_worksheetgrader_save_attempt',args:{attemptid:Number(config.attemptid),answersjson:nativejson,version:Number(revision)}}])[0];
 const controller=NativeEditor.createAutosaveController({initialRevision:Number(config.version)||1,save:saveRemote,onStatus:setStatus});
 const canonicalJson=canonical=>JSON.stringify(canonical||NativeEditor.toNativeDocument(editor.getJSON(),{version:source.version||1,meta:source.meta}));
 editor=NativeEditor.mount({element,documentJson:source,readonly:false,collaboration:false,save:canonical=>controller.saveNow({nativejson:canonicalJson(canonical)}),onUpdate:canonical=>{if(!controller.blocked())controller.schedule({nativejson:canonicalJson(canonical)});}});
 if(form)form.addEventListener('submit',async event=>{if(submitting)return;event.preventDefault();try{const result=await controller.saveNow({nativejson:canonicalJson()});if(!result||result.conflict||result.blocked||result.ok!==true){setStatus('conflict');return;}submitting=true;form.submit();}catch(error){setStatus('error');}});
 setStatus('saved');};return{init};});
