<?php

@mkdir( OW_DIR_USERFILES . 'plugins' . DS . 'event' . DS . 'tmp' . DS );
@chmod( OW_DIR_USERFILES . 'plugins' . DS . 'event' . DS . 'tmp' . DS, 0777 );

@mkdir( OW_DIR_PLUGINFILES . 'event' . DS . 'tmp' . DS );
@chmod( OW_DIR_PLUGINFILES . 'event' . DS . 'tmp' . DS, 0777 );
