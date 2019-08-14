window.onmessage=function(msg) {
      var fra=document.getElementById("lightwindow_iframe");
      var containerdiv = document.getElementById("lightwindow_container");
      var lightwindow_title_bar_close_link_obj = document.getElementById("lightwindow_title_bar_close_link");
      if(msg.data && msg.data.name=="Close" && msg.source==fra.contentWindow) {
          $$('#lightwindow_title_bar_close_link')[0].click();
          }
      };
