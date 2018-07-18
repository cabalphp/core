<!DOCTYPE html>
<html lang="zh-ch">

<head>
  <meta charset="UTF-8">
  <title><?php echo $projectName; ?> API文档</title>
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <link rel="stylesheet" href="//<?php echo $cdn; ?>/docsify/lib/themes/vue.css" title="vue">
  <link rel="stylesheet" href="//<?php echo $cdn; ?>/docsify/lib/themes/dark.css" title="dark" disabled>
  <link rel="stylesheet" href="//<?php echo $cdn; ?>/docsify/lib/themes/buble.css" title="buble" disabled>
  <link rel="stylesheet" href="//<?php echo $cdn; ?>/docsify/lib/themes/pure.css" title="pure" disabled>
  <script src="//<?php echo $cdn; ?>/docsify-plugin-codefund/index.js"></script>
</head>

<body>
  <div id="app">Loading ...</div>
  <script>
    window.$docsify = {
      auto2top: true,
      coverpage: false,
      executeScript: true,
      loadSidebar: true,
      loadNavbar: false,
      mergeNavbar: true,
      subMaxLevel: 2,
      name: '<?php echo $projectName; ?>',
      search: {
        noData: {
          '/': '没有结果!'
        },
        paths: 'auto',
        placeholder: {
          '/': 'Search'
        }
      },
      formatUpdated: '{MM}/{DD} {HH}:{mm}',
      plugins: [
      ]
    }
  </script>
  <script src="//<?php echo $cdn; ?>/docsify/lib/docsify.min.js"></script>
  <script src="//<?php echo $cdn; ?>/docsify/lib/plugins/search.min.js"></script>
  <script src="//<?php echo $cdn; ?>/prismjs/components/prism-bash.min.js"></script>
  <script src="//<?php echo $cdn; ?>/prismjs/components/prism-markdown.min.js"></script>
  <script src="//<?php echo $cdn; ?>/prismjs/components/prism-nginx.min.js"></script>
  <script src="//<?php echo $cdn; ?>/prismjs/components/prism-json.min.js"></script>
</body>

</html>
