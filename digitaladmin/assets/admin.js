'use strict';
const toggle=document.querySelector('.va-menu-button'),menu=document.querySelector('.va-menu');
if(toggle&&menu){toggle.addEventListener('click',()=>{const open=menu.classList.toggle('open');toggle.setAttribute('aria-expanded',String(open));});document.addEventListener('keydown',e=>{if(e.key==='Escape'){menu.classList.remove('open');toggle.setAttribute('aria-expanded','false');}});}
for(const form of document.querySelectorAll('form[data-confirm]'))form.addEventListener('submit',e=>{if(!window.confirm(form.dataset.confirm))e.preventDefault();});
