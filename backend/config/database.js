const { Sequelize } = require('sequelize');

const sequelize = new Sequelize('bozapvpzel9yumdeik3k', 'uxdtm3ws8ybqzqel', 'rlSvJKTJsLJG5EdsmPWW', {
  host: 'bozapvpzel9yumdeik3k-mysql.services.clever-cloud.com',
  dialect: 'mysql',
});

sequelize.authenticate()
  .then(() => {
    console.log('Conectado a la base de datos.');
  })
  .catch(err => {
    console.error('No se puede conectar a la base de datos:', err);
  });

module.exports = sequelize;
