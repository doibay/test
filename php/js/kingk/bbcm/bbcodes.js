!function(n){var u=".bbcmSpoilerBlock";XenForo.BbcmSpoiler=function(t){var i=".bbcm_spoiler_hide",r=".bbcm_spoiler_show",f=".bbcm_spoiler";t.find(".bbcm_spoiler_noscript").addClass("bbcm_spoiler").removeClass("bbcm_spoiler_noscript"),t.find(".button").css("display","inline-block"),t.find(".button").toggle(function(){n(this).parents(u).find(f).show(),n(this).children(r).hide(),n(this).children(i).show()},function(){n(this).parents(u).find(f).hide(),n(this).children(r).show(),n(this).children(i).hide()})},XenForo.register(u,"XenForo.BbcmSpoiler")}(jQuery,this,document);