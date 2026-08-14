const fs=require('fs'), vm=require('vm');
const src=fs.readFileSync(process.cwd() + '/n8-livechat-pro/assets/js/admin.js','utf8');
const a=src.indexOf('  function adminMessageHtml(m, c) {'), b=src.indexOf('\n  function drawThread(stateData) {',a);
if(a<0||b<0) throw new Error('admin echo helpers not found');
const context={
 inbox:{messages:[],lastThreadMessageId:0},
 selectedConversation:()=>({visitor_name:'Visitor'}),
 esc:v=>String(v),fmtDate:v=>String(v),attachmentHtml:m=>String(m.body||''),
 document:{getElementById:()=>null}
};
vm.createContext(context); vm.runInContext(src.slice(a,b),context);
context.appendAdminMessage({id:7,sender_type:'agent',is_private:0,body:'reply',created_at:'now'});
context.appendAdminMessage({id:7,sender_type:'agent',is_private:0,body:'reply',created_at:'now'});
if(context.inbox.messages.length!==1) throw new Error('admin local echo duplicate');
if(context.inbox.lastThreadMessageId!==7) throw new Error('admin latest id not advanced');
console.log('admin optimistic echo test passed');
