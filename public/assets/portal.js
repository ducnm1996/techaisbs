(() => {
 document.querySelectorAll('[data-password-toggle]').forEach(button=>{
  const input=document.getElementById(button.getAttribute('aria-controls'));
  if(!input)return;
  button.addEventListener('click',()=>{
   const visible=input.type==='password';
   input.type=visible?'text':'password';
   button.textContent=visible?'Ẩn':'Hiện';
   button.setAttribute('aria-pressed',String(visible));
   button.setAttribute('aria-label',visible?'Ẩn mật khẩu':'Hiện mật khẩu');
  });
 });
 const form=document.querySelector('[data-reset-form]');
 if(!form)return;
 const token=new URLSearchParams(location.hash.slice(1)).get('token');
 if(token){document.querySelector('#reset-token').value=token;history.replaceState(null,'',location.pathname+location.search);}
})();
