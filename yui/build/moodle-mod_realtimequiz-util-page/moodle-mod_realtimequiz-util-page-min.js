YUI.add("moodle-mod_realtimequiz-util-page",function(n,e){n.namespace("Moodle.mod_realtimequiz.util.page"),n.Moodle.mod_realtimequiz.util.page={CSS:{PAGE:"page"},CONSTANTS:{ACTIONMENUIDPREFIX:"action-menu-",ACTIONMENUBARIDSUFFIX:"-menubar",ACTIONMENUMENUIDSUFFIX:"-menu",PAGEIDPREFIX:"page-",PAGENUMBERPREFIX:M.util.get_string("page","moodle")+" "},SELECTORS:{ACTIONMENU:"div.moodle-actionmenu",ACTIONMENUBAR:".menubar",ACTIONMENUMENU:".menu",ADDASECTION:'[data-action="addasection"]',PAGE:"li.page",INSTANCENAME:".instancename",NUMBER:"h4"},getPageFromComponent:function(e){return n.one(e).ancestor(this.SELECTORS.PAGE,!0)},getPageFromSlot:function(e){return n.one(e).previous(this.SELECTORS.PAGE)},getId:function(e){e=e.get("id").replace(this.CONSTANTS.PAGEIDPREFIX,"");return!("number"!=typeof(e=parseInt(e,10))||!isFinite(e))&&e},setId:function(e,t){e.set("id",this.CONSTANTS.PAGEIDPREFIX+t)},getName:function(e){e=e.one(this.SELECTORS.INSTANCENAME);return e?e.get("firstChild").get("data"):null},getNumber:function(e){e=e.one(this.SELECTORS.NUMBER).get("text").replace(this.CONSTANTS.PAGENUMBERPREFIX,"");return!("number"!=typeof(e=parseInt(e,10))||!isFinite(e))&&e},setNumber:function(e,t){e.one(this.SELECTORS.NUMBER).set("text",this.CONSTANTS.PAGENUMBERPREFIX+t)},getPages:function(){return n.all(n.Moodle.mod_realtimequiz.util.slot.SELECTORS.PAGECONTENT+" "+n.Moodle.mod_realtimequiz.util.slot.SELECTORS.SECTIONUL+" "+this.SELECTORS.PAGE)},isPage:function(e){return!!e&&e.hasClass(this.CSS.PAGE)},isEmpty:function(e){e=e.next("li.activity");return!e||!e.hasClass("slot")},add:function(e){var t=this.getNumber(this.getPageFromSlot(e))+1,i=M.mod_realtimequiz.resource_toolbox.get("config").pagehtml,i=i.replace(/%%PAGENUMBER%%/g,t),o=n.Node.create(i);return YUI().use("dd-drop",function(e){e=new e.DD.Drop({node:o,groups:M.mod_realtimequiz.dragres.groups});o.drop=e}),e.insert(o,"after"),"undefined"!=typeof M.core.actionmenu&&M.core.actionmenu.newDOMNode(o),o},remove:function(e,t){var i=e.previous(n.Moodle.mod_realtimequiz.util.slot.SELECTORS.SLOT);!t&&i&&n.Moodle.mod_realtimequiz.util.slot.removePageBreak(i),e.remove()},reorderPages:function(){var e=this.getPages(),i=0;e.each(function(e){var t;if(this.isEmpty(e))return t=!!e.next("li.slot"),void this.remove(e,t);i++,this.setNumber(e,i),this.setId(e,i)},this),this.reorderActionMenus()},reorderActionMenus:function(){var o=this.getActionMenus();o.each(function(e,t){var t=o.item(t-1),i=0;t&&(i=this.getActionMenuId(t)),this.setActionMenuId(e,t=i+1),e.one(this.SELECTORS.ACTIONMENUBAR).set("id",this.CONSTANTS.ACTIONMENUIDPREFIX+t+this.CONSTANTS.ACTIONMENUBARIDSUFFIX),(i=e.one(this.SELECTORS.ACTIONMENUMENU)).set("id",this.CONSTANTS.ACTIONMENUIDPREFIX+t+this.CONSTANTS.ACTIONMENUMENUIDSUFFIX),i.one(this.SELECTORS.ADDASECTION).set("href",i.one(this.SELECTORS.ADDASECTION).get("href").replace(/\baddsectionatpage=\d+\b/,"addsectionatpage="+t))},this)},getActionMenus:function(){return n.all(n.Moodle.mod_realtimequiz.util.slot.SELECTORS.PAGECONTENT+" "+n.Moodle.mod_realtimequiz.util.slot.SELECTORS.SECTIONUL+" "+this.SELECTORS.ACTIONMENU)},getActionMenuId:function(e){e=e.get("id").replace(this.CONSTANTS.ACTIONMENUIDPREFIX,"");return!("number"!=typeof(e=parseInt(e,10))||!isFinite(e))&&e},setActionMenuId:function(e,t){e.set("id",this.CONSTANTS.ACTIONMENUIDPREFIX+t)}}},"@VERSION@",{requires:["node","moodle-mod_realtimequiz-util-base"]});